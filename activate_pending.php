<?php
declare(strict_types=1);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/inc/TransactionHelper.php';

echo "Double-Entry Catch-up Script v1.1\n";
echo "---------------------------------\n";

try {
    // 1. Activate M-Pesa Requests
    $pending_requests = $conn->query("SELECT * FROM mpesa_requests WHERE status = 'pending'");
    $req_count = 0;
    echo "Processing M-Pesa Requests...\n";
    while ($row = $pending_requests->fetch_assoc()) {
        $id = $row['id'];
        $mock_receipt = 'CATCHUP-' . strtoupper(bin2hex(random_bytes(4)));
        $conn->query("UPDATE mpesa_requests SET status = 'completed', mpesa_receipt = '$mock_receipt' WHERE id = $id");
        $req_count++;
    }
    echo "Done: Activated $req_count Requests.\n";

    // 2. Activate Contributions
    $pending_contribs = $conn->query("SELECT c.* FROM contributions c WHERE c.status = 'pending'");
    $contrib_count = 0;
    echo "Processing Contributions...\n";
    while ($row = $pending_contribs->fetch_assoc()) {
        $cid = $row['contribution_id'];
        $mid = (int)$row['member_id'];
        $amt = (float)$row['amount'];
        $type = $row['contribution_type'];
        $ref = $row['reference_no'];
        $mock_receipt = 'SYNC-' . strtoupper(bin2hex(random_bytes(4)));
        
        // Update contribution status
        $conn->query("UPDATE contributions SET status = 'active' WHERE contribution_id = $cid");
        
        // Record in Ledger
        TransactionHelper::record([
            'member_id'     => $mid,
            'amount'        => $amt,
            'type'          => 'credit',
            'category'      => $type,
            'ref_no'        => $mock_receipt,
            'notes'         => ucfirst($type) . " deposit activated via manual catch-up (Ref: $ref)",
            'method'        => 'mpesa'
        ]);
        
        // Special handling for Registration
        if ($type === 'registration') {
            $conn->query("UPDATE members SET reg_fee_paid = 1, registration_fee_status = 'paid', status = 'active' WHERE member_id = $mid");
        }
        $contrib_count++;
    }
    echo "Done: Activated $contrib_count Contributions.\n";

    // 3. Activate Loan Repayments
    $pending_repayments = $conn->query("SELECT * FROM loan_repayments WHERE status = 'Pending'");
    $loan_count = 0;
    echo "Processing Loan Repayments...\n";
    while ($row = $pending_repayments->fetch_assoc()) {
        $rid = $row['repayment_id'];
        $lid = $row['loan_id'];
        $amt = (float)$row['amount_paid'];
        $mock_receipt = 'LR-SYNC-' . strtoupper(bin2hex(random_bytes(4)));
        
        $conn->query("UPDATE loan_repayments SET status = 'Completed', mpesa_receipt = '$mock_receipt' WHERE repayment_id = $rid");
        
        // Record in Ledger
        $res = $conn->query("SELECT member_id FROM loans WHERE loan_id = $lid");
        $l_row = $res->fetch_assoc();
        if ($l_row) {
            $l_mid = (int)$l_row['member_id'];
            TransactionHelper::record([
                'member_id'     => $l_mid,
                'amount'        => $amt,
                'type'          => 'loan_repayment',
                'ref_no'        => $mock_receipt,
                'notes'         => "Loan Repayment #$lid activated via manual catch-up",
                'method'        => 'mpesa',
                'related_id'    => $lid,
                'related_table' => 'loans'
            ]);
        }
        $loan_count++;
    }
    echo "Done: Activated $loan_count Loan Repayments.\n";

} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "---------------------------------\n";
echo "SUCCESS! Catch-up complete.\n";
