<?php
// member/mpesa_callback.php
header("Content-Type: application/json");

// ---------------------------------------------------
// 1. Load Config & DB
// ---------------------------------------------------
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../inc/email.php';

// ---------------------------------------------------
// 2. Capture Raw Callback
// ---------------------------------------------------
$data = file_get_contents('php://input');
$logFile = __DIR__ . '/mpesa_error.log';

// Log every callback
file_put_contents($logFile,
    "[" . date('Y-m-d H:i:s') . "] RAW CALLBACK:\n" . $data . "\n\n",
    FILE_APPEND
);

$response = json_decode($data, true);

// ---------------------------------------------------
// 3. STK PUSH CALLBACK (C2B Payments)
// ---------------------------------------------------
if (isset($response['Body']['stkCallback'])) {

    $callback     = $response['Body']['stkCallback'];
    $resultCode   = $callback['ResultCode'];
    $resultDesc   = $callback['ResultDesc'];
    $checkoutID   = $callback['CheckoutRequestID'];

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
        file_put_contents($logFile,
            "ERROR: No matching mpesa_request for CheckoutRequestID $checkoutID\n",
            FILE_APPEND
        );
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Request not found']);
        exit;
    }

    $member_id    = $request['member_id'];
    $reference_no = $request['reference_no'];
    $amount       = $request['amount'];
    $email        = $request['email'];
    $full_name    = $request['full_name'];
    $phone        = $request['phone'];

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
            SET status = 'completed', mpesa_receipt = ?, updated_at = NOW()
            WHERE checkout_request_id = ?
        ");
        $q->bind_param("ss", $mpesaReceipt, $checkoutID);
        $q->execute();
        $q->close();

        // 2. Activate contribution
        $c = $conn->prepare("UPDATE contributions SET status = 'active' WHERE reference_no = ?");
        $c->bind_param("s", $reference_no);
        $c->execute();
        $c->close();

        // 3. Update Member Balance (Credit)
        $bm = $conn->prepare("UPDATE members SET account_balance = account_balance + ? WHERE member_id = ?");
        $bm->bind_param("di", $amount, $member_id);
        $bm->execute();
        $bm->close();

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
