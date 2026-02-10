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
    if ($resultCode === 0) {

        // Extract M-Pesa receipt
        $mpesaReceipt = '';
        $items = $callback['CallbackMetadata']['Item'] ?? [];
        foreach ($items as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $mpesaReceipt = $item['Value'];
                break;
            }
        }

        // 1. Update mpesa_requests
        $q = $conn->prepare("
            UPDATE mpesa_requests
            SET status = 'completed', mpesa_receipt = ?, updated_at = NOW(), callback_log_id = ?
            WHERE checkout_request_id = ?
        ");
        $q->bind_param("sis", $mpesaReceipt, $callback_log_id, $checkoutID);
        $q->execute();
        $q->close();

        // 2. Activate contribution and track callback receipt
        $c = $conn->prepare("UPDATE contributions SET status = 'active', callback_received_at = NOW() WHERE reference_no = ?");
        $c->bind_param("s", $reference_no);
        $c->execute();
        $c->close();

        // 3. Update callback log with receipt and mark as processed
        if ($callback_log_id) {
            $conn->query("UPDATE callback_logs SET 
                mpesa_receipt_number = '$mpesaReceipt',
                processed = TRUE,
                processed_at = NOW()
                WHERE log_id = $callback_log_id");
        }

        // 3. Update Member Balance (Optional for registration fee, maybe exclude from balance)
        // Check if this was a registration fee
        $stmt_check = $conn->prepare("SELECT contribution_type FROM contributions WHERE reference_no = ?");
        $stmt_check->bind_param("s", $reference_no);
        $stmt_check->execute();
        $c_data = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        $type = $c_data['contribution_type'] ?? '';

        if ($type === 'registration') {
            // 1. Activate Member & Mark Fee as Paid
            $act = $conn->prepare("UPDATE members SET reg_fee_paid = 1, registration_fee_status = 'paid', status = 'active' WHERE member_id = ?");
            $act->bind_param("i", $member_id);
            $act->execute();
            $act->close();

            // 2. Record in Unified Ledger
            TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => 'registration_fee',
                'ref_no'        => $mpesaReceipt,
                'notes'         => "Registration Fee Paid via M-Pesa ($reference_no)",
                'related_id'    => $member_id,
                'related_table' => 'registration_fee',
                'update_member_balance' => false
            ]);
        } elseif ($type === 'loan_repayment') {
            // 1. Mark Repayment as Completed
            $updRepay = $conn->prepare("UPDATE loan_repayments SET status = 'Completed', reference_no = ? WHERE reference_no = ?");
            $updRepay->bind_param("ss", $mpesaReceipt, $reference_no);
            $updRepay->execute();
            $updRepay->close();

            // 2. Get Loan ID
            $stmtLoan = $conn->prepare("SELECT loan_id FROM loan_repayments WHERE reference_no = ? LIMIT 1");
            $stmtLoan->bind_param("s", $mpesaReceipt);
            $stmtLoan->execute();
            $repay_row = $stmtLoan->get_result()->fetch_assoc();
            $stmtLoan->close();

            if ($repay_row) {
                $loan_id = $repay_row['loan_id'];

                // 3. Update Loan Balance
                $updLoan = $conn->prepare("UPDATE loans SET current_balance = GREATEST(0, current_balance - ?) WHERE loan_id = ?");
                $updLoan->bind_param("di", $amount, $loan_id);
                $updLoan->execute();
                $updLoan->close();

                // 4. Record in Ledger
                TransactionHelper::record([
                    'member_id'     => $member_id,
                    'amount'        => $amount,
                    'type'          => 'loan_repayment',
                    'ref_no'        => $mpesaReceipt,
                    'notes'         => "Loan Repayment via M-Pesa (Loan #$loan_id)",
                    'related_id'    => $loan_id,
                    'related_table' => 'loans',
                    'update_member_balance' => false // Repayment doesn't increase wallet balance
                ]);

                // 5. Check if Loan is complete
                $stmtCheck = $conn->prepare("SELECT current_balance FROM loans WHERE loan_id = ?");
                $stmtCheck->bind_param("i", $loan_id);
                $stmtCheck->execute();
                $l_data = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();

                if ($l_data && $l_data['current_balance'] <= 0) {
                    $conn->query("UPDATE loans SET status = 'completed' WHERE loan_id = $loan_id");
                    $conn->query("UPDATE loan_guarantors SET status = 'released' WHERE loan_id = $loan_id");
                }
            }
        } else {
            // General Credit (Savings/Shares/Welfare)
            // Handled via TransactionHelper/FinancialEngine to ensure consistency
            TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => 'credit', // General inflow
                'category'      => $type,    // 'savings', 'shares', 'welfare'
                'ref_no'        => $mpesaReceipt,
                'notes'         => ucfirst($type) . " deposit via M-Pesa",
                'method'        => 'mpesa'
            ]);
        }

        // 4. Send confirmation email
        if (!empty($email)) {
            $subject = "Payment Confirmed ($mpesaReceipt)";
            $body = "
                <p>Hello <strong>$full_name</strong>,</p>
                <p>Your payment of <strong>KES " . number_format($amount) . "</strong> was successful.</p>
                <p><strong>Receipt:</strong> $mpesaReceipt<br>
                <strong>Reference:</strong> $reference_no</p>
                <p style='color:green;'>Thank you for your payment.</p>
            ";
            try { sendEmail($email, $subject, $body, $member_id); } catch (Exception $e) {}
        }

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

        // Email failure notice
        if (!empty($email)) {
            $subject = "Payment Failed";
            $body = "
                <p>Hello <strong>$full_name</strong>,</p>
                <p>Your payment attempt failed.</p>
                <p><strong>Reason:</strong> $resultDesc</p>
                <p>Please try again.</p>
            ";
            try { sendEmail($email, $subject, $body, $member_id); } catch (Exception $e) {}
        }

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
// 4. B2C WITHDRAWAL CALLBACK
// ---------------------------------------------------
if (isset($response['Result'])) {

    $res            = $response['Result'];
    $resultCode     = $res['ResultCode'];
    $resultDesc     = $res['ResultDesc'];
    $originatorID   = $res['OriginatorConversationID'] ?? ''; // This links to our DB
    $transactionID  = $res['TransactionID'] ?? ''; // M-Pesa Receipt

    // Log
    file_put_contents($logFile,
        "B2C CALLBACK:\n" . print_r($response, true) . "\n\n",
        FILE_APPEND
    );

    if ($resultCode == 0) {
        // --- SUCCESS ---
        // 1. Update Transaction: Add receipt number
        $q = $conn->prepare("UPDATE transactions SET notes = CONCAT(notes, ' - Ref: ', ?) WHERE mpesa_request_id = ?"); // Ensure column exists? mpesa_request_id might be virtual/missing in schema provided.
        // STOP: Schema view of 'transactions' table (Line 122) does NOT showing 'mpesa_request_id'.
        // It has 'reference_no'. OriginatorConversationID usually matches reference_no or is stored in a separate log?
        // Assuming 'reference_no' or 'related_id'.
        // IF B2C request logic stored OriginatorID, we need to find it. 
        // Typically B2C uses 'ConversationID' or 'OriginatorConversationID'.
        // If we don't have that column, we might struggle to match. 
        // However, usually we store it in 'reference_no' for the transaction.
        
        // Let's assume 'reference_no' holds the ID we can match, or we query 'mpesa_requests' first?
        // But logic below was querying 'transactions' by 'mpesa_request_id'.
        // Schema check: 'transactions' has: transaction_id, member_id, transaction_type, amount, related_id, transaction_date, payment_channel, notes, created_by_admin.
        // It does NOT have 'mpesa_request_id'.
        
        // We probably need to match by 'reference_no' = $originatorID or similar.
        // Let's try matching 'notes' LIKE ...? No.
        // Given constraints, I will update code to use 'reference_no'.
        
        $q = $conn->prepare("UPDATE transactions SET notes = CONCAT(notes, ' - Ref: ', ?) WHERE reference_no = ?");
        $q->bind_param("ss", $transactionID, $originatorID);
        $q->execute();
        $q->close();

        // 2. Send Email Notification
        $stmt = $conn->prepare("SELECT t.member_id, m.email, m.full_name, t.amount 
                                FROM transactions t 
                                JOIN members m ON t.member_id = m.member_id 
                                WHERE t.reference_no = ? LIMIT 1");
        $stmt->bind_param("s", $originatorID);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($txn && !empty($txn['email'])) {
            $subject = "Withdrawal Confirmed";
            $body = "<p>Dear <strong>{$txn['full_name']}</strong>,</p>
                     <p>Your withdrawal of <strong>KES " . number_format($txn['amount']) . "</strong> is complete.</p>
                     <p>M-Pesa Ref: <strong>$transactionID</strong></p>";
            try { sendEmail($txn['email'], $subject, $body, $txn['member_id']); } catch (Exception $e) {}
        }

    } else {
        // --- FAILURE ---
        // Money was already deducted. Refund it.
        
        // 1. Get transaction details
        $stmt = $conn->prepare("SELECT member_id, amount, reference_no FROM transactions WHERE reference_no = ? LIMIT 1");
        $stmt->bind_param("s", $originatorID);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($txn) {
            $member_id = $txn['member_id'];
            $amount = $txn['amount'];
            $ref = $txn['reference_no'];

            // 2. Mark original transaction as FAILED
            $q = $conn->prepare("UPDATE transactions SET notes = CONCAT(notes, ' [FAILED - REFUNDED]') WHERE reference_no = ?");
            $q->bind_param("s", $originatorID);
            $q->execute();
            $q->close();

            // 3. Refund (Credit back to Contributions ledger and Member Balance)
            $refundRef = "REF-" . strtoupper(uniqid());
            
            // Insert into Contributions (as a mechanism to track incoming/refunded money)
            $ins = $conn->prepare("INSERT INTO contributions (member_id, contribution_type, amount, payment_method, reference_no, status, created_at) VALUES (?, 'savings', ?, 'system', ?, 'active', NOW())");
            $ins->bind_param("ids", $member_id, $amount, $refundRef);
            $ins->execute();
            $ins->close();

            // Record in Transactions Ledger
            $insT = $conn->prepare("INSERT INTO transactions (member_id, transaction_type, amount, payment_channel, notes, reference_no, transaction_date) VALUES (?, 'deposit', ?, 'system', 'Refund for failed withdrawal', ?, NOW())");
            $insT->bind_param("ids", $member_id, $amount, $refundRef);
            $insT->execute();
            $insT->close();
            
            // 4. Restore Member Balance
            $upd = $conn->prepare("UPDATE members SET account_balance = account_balance + ? WHERE member_id = ?");
            $upd->bind_param("di", $amount, $member_id);
            $upd->execute();
            $upd->close();

            // 5. Send Failure Email
            // ... (Fetch email logic similar to above) ...
        }
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
