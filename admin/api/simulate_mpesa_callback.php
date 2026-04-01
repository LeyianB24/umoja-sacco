<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/notification_helpers.php';
require_once __DIR__ . '/../../inc/email.php';

// Security: Must be admin
require_admin();

header("Content-Type: application/json");

// Capture JSON data
$data = json_decode(file_get_contents('php://input'), true);
$checkoutID = $data['checkout_request_id'] ?? null;

if (!$checkoutID) {
    echo json_encode(['success' => false, 'message' => 'Missing CheckoutRequestID']);
    exit;
}

try {
    // 1. Look up the original request
    $stmt = $conn->prepare("
        SELECT r.id, r.member_id, r.amount, r.reference_no, r.status,
               m.email, m.full_name, m.phone, m.member_reg_no
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
        throw new Exception("M-Pesa request not found for ID: $checkoutID");
    }

    if ($request['status'] === 'completed') {
        throw new Exception("Transaction is already completed.");
    }

    // Safety: Check if this reference is already in the ledger (idempotency)
    $ref = $request['reference_no'];
    $checkLedger = $conn->prepare("SELECT transaction_id FROM ledger_transactions WHERE reference_no = ? LIMIT 1");
    $checkLedger->bind_param("s", $ref);
    $checkLedger->execute();
    if ($checkLedger->get_result()->num_rows > 0) {
        throw new Exception("Transaction already processed (found in ledger).");
    }
    $checkLedger->close();

    $member_id    = (int)$request['member_id'];
    $reference_no = $request['reference_no'];
    $amount       = (float)$request['amount'];
    $email        = $request['email'];
    $full_name    = $request['full_name'];
    $phone        = $request['phone'];

    // Simulate an M-Pesa Receipt Number
    $mpesaReceipt = 'SIM-' . strtoupper(bin2hex(random_bytes(4)));

    // Begin Simulation Transaction
    $conn->begin_transaction();

    // 2. Create a simulated callback log
    $callback_type = 'STK_PUSH_SIMULATED';
    $resultCode = 0;
    $resultDesc = "Successful Simulation (Admin Activated)";
    $raw_payload = json_encode([
        'Body' => [
            'stkCallback' => [
                'ResultCode' => 0,
                'ResultDesc' => $resultDesc,
                'CheckoutRequestID' => $checkoutID,
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => $amount],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => $mpesaReceipt],
                        ['Name' => 'TransactionDate', 'Value' => date('YmdHis')],
                        ['Name' => 'PhoneNumber', 'Value' => $phone]
                    ]
                ]
            ]
        ]
    ]);

    $log_stmt = $conn->prepare("INSERT INTO callback_logs 
        (callback_type, raw_payload, checkout_request_id, result_code, result_desc, processed, processed_at, member_id, amount, mpesa_receipt_number) 
        VALUES (?, ?, ?, ?, ?, TRUE, NOW(), ?, ?, ?)");
    $log_stmt->bind_param("sssisdds", $callback_type, $raw_payload, $checkoutID, $resultCode, $resultDesc, $member_id, $amount, $mpesaReceipt);
    $log_stmt->execute();
    $callback_log_id = $conn->insert_id;
    $log_stmt->close();

    // 3. Update mpesa_requests
    $q = $conn->prepare("UPDATE mpesa_requests SET status = 'completed', mpesa_receipt = ?, updated_at = NOW(), callback_log_id = ? WHERE checkout_request_id = ?");
    $q->bind_param("sis", $mpesaReceipt, $callback_log_id, $checkoutID);
    $q->execute();
    $q->close();

    // 4. Activate contribution
    $c = $conn->prepare("UPDATE contributions SET status = 'active', callback_received_at = NOW() WHERE reference_no = ?");
    $c->bind_param("s", $reference_no);
    $c->execute();
    $c->close();

    // 5. Look up contribution type to determine ledger logic
    $stmt_check = $conn->prepare("SELECT contribution_type FROM contributions WHERE reference_no = ?");
    $stmt_check->bind_param("s", $reference_no);
    $stmt_check->execute();
    $c_data = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    $type = $c_data['contribution_type'] ?? 'savings';

    // 6. Record in Ledger via TransactionHelper
    if ($type === 'registration') {
        $act = $conn->prepare("UPDATE members SET reg_fee_paid = 1, registration_fee_status = 'paid', status = 'active' WHERE member_id = ?");
        $act->bind_param("i", $member_id);
        $act->execute();
        $act->close();

        TransactionHelper::record([
            'member_id'     => $member_id,
            'amount'        => $amount,
            'type'          => 'registration_fee',
            'ref_no'        => $mpesaReceipt,
            'notes'         => "Registration Fee Paid via Simulation ($reference_no)",
            'method'        => 'mpesa',
            'related_id'    => $member_id,
            'related_table' => 'registration_fee'
        ]);
    } elseif ($type === 'loan_repayment') {
        $stmtLR = $conn->prepare("SELECT loan_id FROM loan_repayments WHERE reference_no = ? LIMIT 1");
        $stmtLR->bind_param("s", $reference_no);
        $stmtLR->execute();
        $lr_row = $stmtLR->get_result()->fetch_assoc();
        $stmtLR->close();
        
        if ($lr_row) {
            $loan_id = (int)$lr_row['loan_id'];
            TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => 'loan_repayment',
                'ref_no'        => $mpesaReceipt,
                'notes'         => "Loan Repayment Simulation (Loan #$loan_id)",
                'method'        => 'mpesa',
                'related_id'    => $loan_id,
                'related_table' => 'loans'
            ]);
            $conn->query("UPDATE loan_repayments SET status = 'Completed', mpesa_receipt = '$mpesaReceipt' WHERE reference_no = '$reference_no'");
        }
    } else {
        TransactionHelper::record([
            'member_id'     => $member_id,
            'amount'        => $amount,
            'type'          => 'credit',
            'category'      => $type,
            'ref_no'        => $mpesaReceipt,
            'notes'         => ucfirst($type) . " deposit via Simulation",
            'method'        => 'mpesa'
        ]);
    }

    $conn->commit();

    // 7. Send confirmation (Use deposit_success template logic)
    send_notification($conn, (int)$member_id, 'deposit_success', [
        'amount' => $amount,
        'reference' => $mpesaReceipt
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Successfully simulated M-Pesa receipt for $full_name. Receipt: $mpesaReceipt"
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn->connect_errno === 0 && $conn->ping()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
