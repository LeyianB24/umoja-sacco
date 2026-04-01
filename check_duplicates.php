<?php
/**
 * check_duplicates.php
 * Standalone audit tool (v4)
 */

$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "--- 🔍 DOUBLE ENTRY AUDIT START ---\n\n";

function check($conn, $label, $sql) {
    echo "[$label] Running...\n";
    $res = $conn->query($sql);
    if (!$res) {
        echo "  ❌ SQL Error: " . $conn->error . "\n";
        return;
    }
    if ($res->num_rows > 0) {
        while($row = $res->fetch_assoc()) {
            echo "  ⚠️ Found: " . json_encode($row) . "\n";
        }
    } else {
        echo "  ✅ clean.\n";
    }
}

// 1. Duplicate reference_no in contributions
check($conn, "1. Duplicate Contribution References", 
    "SELECT reference_no, COUNT(*) as cnt FROM contributions WHERE status='active' AND reference_no != '' GROUP BY reference_no HAVING cnt > 1");

// 2. Duplicate Receipts
check($conn, "2. Duplicate processed callbacks", 
    "SELECT mpesa_receipt_number, COUNT(*) as cnt FROM callback_logs WHERE processed=1 AND mpesa_receipt_number != '' GROUP BY mpesa_receipt_number HAVING cnt > 1");

// 3. Duplicate Ledger Pairs
// Look for Member + Date + Credit + Debit combos that appear more than once (pair = 2 lines)
check($conn, "3. Identical Ledger Entries (Potential Multi-Post)", 
    "SELECT member_id, entry_date, credit, debit, category, COUNT(*) as cnt FROM ledger_entries GROUP BY member_id, entry_date, credit, debit, category HAVING cnt > 2");

// 4. Multiple completed mpesa_requests
check($conn, "4. Duplicate completed M-Pesa Requests", 
    "SELECT checkout_request_id, COUNT(*) as cnt FROM mpesa_requests WHERE status='completed' GROUP BY checkout_request_id HAVING cnt > 1");

echo "\n--- AUDIT COMPLETE ---\n";
?>
