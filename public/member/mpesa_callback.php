<?php
// member/mpesa_callback.php
header("Content-Type: application/json");

// ---------------------------------------------------
// 1. Load Config & DB
// ---------------------------------------------------
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/app_config.php';
$env_config = require_once __DIR__ . '/../../config/environment.php';
require_once __DIR__ . '/../../inc/email.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

// ---------------------------------------------------
// 2. Capture Raw Callback & Log to Database
// ---------------------------------------------------
$data = file_get_contents('php://input');
$logFile = __DIR__ . '/mpesa_error.log';

// PRODUCTION: Log to database for audit trail
$callback_log_id = null;
try {
    $log_stmt = $conn->prepare("INSERT INTO callback_logs (callback_type, raw_payload) VALUES (?, ?)");
    $callback_type = 'UNKNOWN';
    $log_stmt->bind_param("ss", $callback_type, $data);
    $log_stmt->execute();
    $callback_log_id = $conn->insert_id;
} catch (Exception $e) {
    // Fallback to file logging if database fails
    file_put_contents($logFile,
        "[" . date('Y-m-d H:i:s') . "] DB LOG FAILED: {$e->getMessage()}\n",
        FILE_APPEND
    );
}

// Also keep file logging as backup
file_put_contents($logFile,
    "[" . date('Y-m-d H:i:s') . "] RAW CALLBACK (Log ID: $callback_log_id):\n" . $data . "\n\n",
    FILE_APPEND
);

$response = json_decode($data, true);

// Validate JSON payload
if (json_last_error() !== JSON_ERROR_NONE) {
    $error = "Invalid JSON: " . json_last_error_msg();
    if ($callback_log_id) {
        $conn->query("UPDATE callback_logs SET last_error = '$error', processing_attempts = 1 WHERE log_id = $callback_log_id");
    }
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $error\n\n", FILE_APPEND);
    echo json_encode(["ResultCode" => 1, "ResultDesc" => $error]);
    exit;
}

// ---------------------------------------------------
// 3. STK PUSH CALLBACK (C2B Payments)
// ---------------------------------------------------
if (isset($response['Body']['stkCallback'])) {

    $callback     = $response['Body']['stkCallback'];
    $resultCode   = $callback['ResultCode'];
    $resultDesc   = $callback['ResultDesc'];
    $checkoutID   = $callback['CheckoutRequestID'];
    $merchantReqID = $callback['MerchantRequestID'] ?? null;

    // Update callback log with STK PUSH metadata
    if ($callback_log_id) {
        $update_log = $conn->prepare("UPDATE callback_logs SET 
            callback_type = 'STK_PUSH',
            merchant_request_id = ?,
            checkout_request_id = ?,
            result_code = ?,
            result_desc = ?
            WHERE log_id = ?");
        $update_log->bind_param("ssisi", $merchantReqID, $checkoutID, $resultCode, $resultDesc, $callback_log_id);
        $update_log->execute();
    }

    // ---------------------------------------------------
    // Look up original request
    // ---------------------------------------------------
    $stmt = $conn->prepare("
        SELECT r.request_id, r.member_id, r.amount, r.reference_no,
               m.email, m.full_name, m.phone
        FROM mpesa_requests r
        JOIN members m ON r.member_id = m.member_id
        WHERE r.checkout_request_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $checkoutID);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        $error_msg = "No matching mpesa_request for CheckoutRequestID $checkoutID";
        file_put_contents($logFile, "ERROR: $error_msg\n", FILE_APPEND);
        
        // Update callback log with error
        if ($callback_log_id) {
            $conn->query("UPDATE callback_logs SET 
                last_error = '$error_msg',
                processing_attempts = processing_attempts + 1
                WHERE log_id = $callback_log_id");
        }
        
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Request not found']);
        exit;
    }

    $member_id    = $request['member_id'];
    $reference_no = $request['reference_no'];
    $amount       = $request['amount'];
    $email        = $request['email'];
    $full_name    = $request['full_name'];
    $phone        = $request['phone'];

    // Update callback log with member info
    if ($callback_log_id) {
        $conn->query("UPDATE callback_logs SET 
            member_id = $member_id,
            amount = $amount
            WHERE log_id = $callback_log_id");
    }

    // ---------------------------------------------------
    // SUCCESSFUL PAYMENT
    // ---------------------------------------------------
    // ---------------------------------------------------
    // SUCCESSFUL PAYMENT
    // ---------------------------------------------------
    if ($resultCode === 0) {

        // 1. Idempotency Check: Check if this CheckoutRequestID already has a completed log
        $check_processed = $conn->prepare("SELECT log_id FROM callback_logs WHERE checkout_request_id = ? AND processed = TRUE LIMIT 1");
        $check_processed->bind_param("s", $checkoutID);
        $check_processed->execute();
        if ($check_processed->get_result()->fetch_assoc()) {
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Already Processed']);
            exit;
        }

        // Extract M-Pesa receipt
        $mpesaReceipt = '';
        $items = $callback['CallbackMetadata']['Item'] ?? [];
        foreach ($items as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $mpesaReceipt = $item['Value'];
                break;
            }
        }

        // 2. Update status to processed in log first (prevent race conditions)
        if ($callback_log_id) {
            $conn->query("UPDATE callback_logs SET 
                mpesa_receipt_number = '$mpesaReceipt',
                processed = TRUE,
                processed_at = NOW(),
                member_id = $member_id,
                amount = $amount
                WHERE log_id = $callback_log_id");
        }

        // 3. Update mpesa_requests
        $q = $conn->prepare("
            UPDATE mpesa_requests
            SET status = 'completed', mpesa_receipt = ?, updated_at = NOW(), callback_log_id = ?
            WHERE checkout_request_id = ?
        ");
        $q->bind_param("sis", $mpesaReceipt, $callback_log_id, $checkoutID);
        $q->execute();
        $q->close();

        // 4. Activate contribution and track callback receipt
        $c = $conn->prepare("UPDATE contributions SET status = 'active', callback_received_at = NOW() WHERE reference_no = ?");
        $c->bind_param("s", $reference_no);
        $c->execute();
        $c->close();

        // 5. Look up contribution type
        $stmt_check = $conn->prepare("SELECT contribution_type FROM contributions WHERE reference_no = ?");
        $stmt_check->bind_param("s", $reference_no);
        $stmt_check->execute();
        $c_data = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        $type = $c_data['contribution_type'] ?? '';

        // 6. RECORD IN LEDGER (This replaces ALL manual balance updates)
        if ($type === 'registration') {
            // Activate member metadata (Non-financial state)
            $act = $conn->prepare("UPDATE members SET reg_fee_paid = 1, registration_fee_status = 'paid', status = 'active' WHERE member_id = ?");
            $act->bind_param("i", $member_id);
            $act->execute();
            $act->close();
            
            TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => 'registration_fee',
                'ref_no'        => $mpesaReceipt,
                'notes'         => "Registration Fee Paid via M-Pesa ($reference_no)",
                'method'        => 'mpesa',
                'related_id'    => $member_id,
                'related_table' => 'registration_fee'
            ]);
        } elseif ($type === 'loan_repayment') {
            // Get Loan ID from the repayment request
            $stmtLR = $conn->prepare("SELECT loan_id FROM loan_repayments WHERE reference_no = ? LIMIT 1");
            $stmtLR->bind_param("s", $reference_no);
            $stmtLR->execute();
            $l_row = $stmtLR->get_result()->fetch_assoc();
            $stmtLR->close();
            
            if ($l_row) {
                $loan_id = (int)$l_row['loan_id'];
                
                // Record repayment in ledger (FinancialEngine handles loan balance sync)
                TransactionHelper::record([
                    'member_id'     => $member_id,
                    'amount'        => $amount,
                    'type'          => 'loan_repayment',
                    'ref_no'        => $mpesaReceipt,
                    'notes'         => "Loan Repayment via M-Pesa (Loan #$loan_id)",
                    'method'        => 'mpesa',
                    'related_id'    => $loan_id,
                    'related_table' => 'loans'
                ]);
                
                // Update specific repayment record status (metadata)
                $conn->query("UPDATE loan_repayments SET status = 'Completed', mpesa_receipt = '$mpesaReceipt' WHERE reference_no = '$reference_no'");
            }
        } else {
            // General Credit (Savings/Shares/Welfare)
            TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => 'credit',
                'category'      => $type,
                'ref_no'        => $mpesaReceipt,
                'notes'         => ucfirst($type) . " deposit via M-Pesa",
                'method'        => 'mpesa'
            ]);
        }

        // 4. Send confirmation email
        // 4. Send confirmation notification (and email if available)
        $subject = "Payment Confirmed ($mpesaReceipt)";
        $body = "
            <p>Hello <strong>$full_name</strong>,</p>
            <p>Your payment of <strong>KES " . number_format($amount) . "</strong> was successful.</p>
            <p><strong>Receipt:</strong> $mpesaReceipt<br>
            <strong>Reference:</strong> $reference_no</p>
            <p style='color:green;'>Thank you for your payment.</p>
        ";
        // sendEmail handles notification insertion internally.
        // We pass email (can be empty) and member_id (required for notification).
        try { sendEmail($email, $subject, $body, $member_id); } catch (Exception $e) {}

        file_put_contents($logFile,
            "SUCCESS: Payment $mpesaReceipt processed for Ref $reference_no\n",
            FILE_APPEND
        );
    }

    // ---------------------------------------------------
    // FAILED PAYMENT
    // ---------------------------------------------------
    else {

        // Update mpesa_requests
        $q = $conn->prepare("
            UPDATE mpesa_requests
            SET status = 'failed', updated_at = NOW()
            WHERE checkout_request_id = ?
        ");
        $q->bind_param("s", $checkoutID);
        $q->execute();
        $q->close();

        // Mark contribution as failed
        $c = $conn->prepare("UPDATE contributions SET status = 'failed' WHERE reference_no = ?");
        $c->bind_param("s", $reference_no);
        $c->execute();
        $c->close();

        // Mark transaction ledger
        $t = $conn->prepare("
            UPDATE transactions 
            SET notes = CONCAT(notes, ' [FAILED]')
            WHERE reference_no = ?
        ");
        $t->bind_param("s", $reference_no);
        $t->execute();
        $t->close();

        // Email failure notice (and internal notification)
        $subject = "Payment Failed";
        $body = "
            <p>Hello <strong>$full_name</strong>,</p>
            <p>Your payment attempt failed.</p>
            <p><strong>Reason:</strong> $resultDesc</p>
            <p>Please try again.</p>
        ";
        try { sendEmail($email, $subject, $body, $member_id); } catch (Exception $e) {}

        file_put_contents($logFile,
            "FAILED: Ref $reference_no - Reason: $resultDesc\n",
            FILE_APPEND
        );
    }

    // Always respond
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'STK Callback Received']);
    exit;
}

// ---------------------------------------------------
// 4. B2C WITHDRAWAL CALLBACK (Standardized)
// ---------------------------------------------------
if (isset($response['Result'])) {

    $res            = $response['Result'];
    $resultCode     = $res['ResultCode'];
    $resultDesc     = $res['ResultDesc'];
    $originatorID   = $res['OriginatorConversationID'] ?? ''; 
    $transactionID  = $res['TransactionID'] ?? ''; // M-Pesa Receipt
    $mpesaConvID    = $res['ConversationID'] ?? '';

    // Log the callback metadata in callback_logs
    if ($callback_log_id) {
        $update_log = $conn->prepare("UPDATE callback_logs SET 
            callback_type = 'B2C_WITHDRAWAL',
            merchant_request_id = ?, -- Using ConversationID as merchant ref
            checkout_request_id = ?, -- Using OriginatorConversationID
            result_code = ?,
            result_desc = ?
            WHERE log_id = ?");
        $update_log->bind_param("ssisi", $mpesaConvID, $originatorID, $resultCode, $resultDesc, $callback_log_id);
        $update_log->execute();
    }

    // Look up our withdrawal request
    $stmt = $conn->prepare("SELECT * FROM withdrawal_requests WHERE ref_no = ? LIMIT 1");
    $stmt->bind_param("s", $originatorID);
    $stmt->execute();
    $withdraw = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($withdraw) {
        $member_id = $withdraw['member_id'];
        $amount = $withdraw['amount'];
        $source = $withdraw['source_ledger'];
        
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $engine = new FinancialEngine($conn);

        // Update withdrawal_requests with callback data
        $stmt_upd = $conn->prepare("UPDATE withdrawal_requests SET 
            result_code = ?, 
            result_desc = ?, 
            mpesa_receipt = ?, 
            callback_received_at = NOW(),
            status = ?
            WHERE ref_no = ?");
        
        $new_status = ($resultCode == 0) ? 'completed' : 'failed';
        $stmt_upd->bind_param("issss", $resultCode, $resultDesc, $transactionID, $new_status, $originatorID);
        $stmt_upd->execute();
        $stmt_upd->close();

        if ($resultCode == 0) {
            // --- SUCCESS ---
            
            // Finalize Ledger Transaction (Debit Clearing, Credit Float)
            $engine->transact([
                'member_id'   => $member_id,
                'amount'      => $amount,
                'action_type' => 'withdrawal_finalize',
                'method'      => 'mpesa',
                'reference'   => $transactionID, // Use MPesa receipt as final ref
                'notes'       => "Withdrawal Successful ($originatorID)",
                'related_id'  => $withdraw['withdrawal_id'],
                'related_table' => 'withdrawal_requests'
            ]);

            // Notify Member
            $stmt_m = $conn->prepare("SELECT email, full_name FROM members WHERE member_id = ?");
            $stmt_m->bind_param("i", $member_id);
            $stmt_m->execute();
            $m = $stmt_m->get_result()->fetch_assoc();
            $stmt_m->close();

            if ($m && !empty($m['email'])) {
                $subject = "Withdrawal Successful";
                $body = "<p>Dear <strong>{$m['full_name']}</strong>,</p>
                         <p>Your withdrawal of <strong>KES " . number_format($amount) . "</strong> has been processed successfully.</p>
                         <p>M-Pesa Receipt: <strong>$transactionID</strong></p>
                         <p>Thank you for choosing " . SITE_NAME . ".</p>";
                try { sendEmail($m['email'], $subject, $body, $member_id); } catch (Exception $e) {}
            }

            file_put_contents($logFile, "SUCCESS: B2C Withdrawal $originatorID completed with $transactionID\n", FILE_APPEND);
        } else {
            // --- FAILURE ---
            
            // Revert Ledger Transaction (Debit Clearing, Credit back to Source Account)
            $ledger_cat = FinancialEngine::CAT_WALLET;
            if ($source === 'savings') $ledger_cat = FinancialEngine::CAT_SAVINGS;
            elseif ($source === 'shares') $ledger_cat = FinancialEngine::CAT_SHARES;
            elseif ($source === 'welfare') $ledger_cat = FinancialEngine::CAT_WELFARE;

            $engine->transact([
                'member_id'   => $member_id,
                'amount'      => $amount,
                'action_type' => 'withdrawal_revert',
                'dest_cat'    => $ledger_cat,
                'reference'   => 'REV-' . $originatorID,
                'notes'       => "Withdrawal Failed Reversion ($originatorID) - $resultDesc"
            ]);

            file_put_contents($logFile, "FAILED: B2C Withdrawal $originatorID failed with reason: $resultDesc. Funds Reverted.\n", FILE_APPEND);
            
            // Notify Member of failure
            $stmt_m = $conn->prepare("SELECT email, full_name FROM members WHERE member_id = ?");
            $stmt_m->bind_param("i", $member_id);
            $stmt_m->execute();
            $m = $stmt_m->get_result()->fetch_assoc();
            $stmt_m->close();

            if ($m && !empty($m['email'])) {
                $subject = "Withdrawal Failed";
                $body = "<p>Dear <strong>{$m['full_name']}</strong>,</p>
                         <p>Your withdrawal request of <strong>KES " . number_format($amount) . "</strong> could not be processed.</p>
                         <p><strong>Reason:</strong> $resultDesc</p>
                         <p>The funds have been returned to your account.</p>
                         <p>Please contact support if you have any questions.</p>";
                try { sendEmail($m['email'], $subject, $body, $member_id); } catch (Exception $e) {}
            }
        }
        
        if ($callback_log_id) {
            $conn->query("UPDATE callback_logs SET processed = TRUE, processed_at = NOW(), member_id = $member_id, amount = $amount WHERE log_id = $callback_log_id");
        }
    } else {
        file_put_contents($logFile, "ERROR: No matching withdrawal_request for B2C OriginatorID $originatorID\n", FILE_APPEND);
    }

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'B2C Callback Received']);
    exit;
}
// ---------------------------------------------------
// 5. Invalid/Unknown Callback
// ---------------------------------------------------
file_put_contents($logFile,
    "UNKNOWN CALLBACK RECEIVED\n\n",
    FILE_APPEND
);

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback Received']);
exit;
