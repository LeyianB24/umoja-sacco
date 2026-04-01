<?php
require_once 'config/app.php';

$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "--- 🔍 DOUBLE ENTRY AUDIT START ---\n\n";

// 1. Duplicate reference_no in contributions
echo "[1] Checking Contributions for duplicate reference_no...\n";
$sql = "SELECT reference_no, COUNT(*) as cnt, GROUP_CONCAT(contribution_id) as ids 
        FROM contributions 
        WHERE status = 'active' AND reference_no != '' AND reference_no IS NOT NULL 
        GROUP BY reference_no HAVING cnt > 1";
$res = $conn->query($sql);
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) echo "  - DUPLICATE REF: {$row['reference_no']} (Count: {$row['cnt']}, IDs: {$row['ids']})\n";
} else {
    echo "  - No duplicate active contributions.\n";
}

// 2. Duplicate M-Pesa Receipt Numbers in callback_logs
echo "\n[2] Checking M-Pesa Receipts in callback_logs...\n";
$sql = "SELECT mpesa_receipt_number, COUNT(*) as cnt, GROUP_CONCAT(log_id) as ids 
        FROM callback_logs 
        WHERE processed = 1 AND mpesa_receipt_number != '' AND mpesa_receipt_number IS NOT NULL 
        GROUP BY mpesa_receipt_number HAVING cnt > 1";
$res = $conn->query($sql);
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) echo "  - DUPLICATE RECEIPT: {$row['mpesa_receipt_number']} (Count: {$row['cnt']}, IDs: {$row['ids']})\n";
} else {
    echo "  - No duplicate processed receipts.\n";
}

// 3. Duplicate Ledger Entries (Potential accidental double posts)
echo "\n[3] Checking Ledger for exact identical entries...\n";
$sql = "SELECT transaction_id, member_id, debit, credit, category, COUNT(*) as cnt 
        FROM ledger_entries 
        GROUP BY transaction_id, member_id, debit, credit, category, entry_date 
        HAVING cnt > 1";
$res = $conn->query($sql);
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) echo "  - POTENTIAL LEDGER DOUBLE: Txn #{$row['transaction_id']} (Type: {$row['category']}, Count: {$row['cnt']})\n";
} else {
    echo "  - No exact identical ledger entries found.\n";
}

// 4. Multiple 'completed' mpesa_requests for same CheckoutRequestID (just in case)
echo "\n[4] Checking mpesa_requests for duplicate completed IDs...\n";
$sql = "SELECT checkout_request_id, COUNT(*) as cnt 
        FROM mpesa_requests 
        WHERE status = 'completed' 
        GROUP BY checkout_request_id HAVING cnt > 1";
$res = $conn->query($sql);
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) echo "  - DUPLICATE COMPLETED REQUEST: {$row['checkout_request_id']} (Count: {$row['cnt']})\n";
} else {
    echo "  - No duplicate completed mpesa_requests.\n";
}

echo "\n--- AUDIT COMPLETE ---\n";
?>
