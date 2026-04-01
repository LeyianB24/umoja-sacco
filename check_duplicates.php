<?php
/**
 * check_duplicates.php
 * Standalone audit tool (v5 - No functions, direct execution)
 */

$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "--- 🔍 DOUBLE ENTRY AUDIT START ---\n\n";

// 1. Contributions
echo "[1] Checking Contributions...\n";
$sql1 = "SELECT reference_no, COUNT(*) as cnt FROM contributions WHERE status='active' AND reference_no != '' GROUP BY reference_no HAVING cnt > 1";
$res1 = $conn->query($sql1);
if ($res1 && $res1->num_rows > 0) {
    while($row = $res1->fetch_assoc()) echo "  ⚠️ Duplicate Reference: " . $row['reference_no'] . " (Found " . $row['cnt'] . ")\n";
} else echo "  ✅ clean.\n";

// 2. Callbacks
echo "\n[2] Checking Callbacks...\n";
$sql2 = "SELECT mpesa_receipt_number, COUNT(*) as cnt FROM callback_logs WHERE processed=1 AND mpesa_receipt_number != '' GROUP BY mpesa_receipt_number HAVING cnt > 1";
$res2 = $conn->query($sql2);
if ($res2 && $res2->num_rows > 0) {
    while($row = $res2->fetch_assoc()) echo "  ⚠️ Duplicate Receipt: " . $row['mpesa_receipt_number'] . " (Found " . $row['cnt'] . ")\n";
} else echo "  ✅ clean.\n";

// 3. Ledger
echo "\n[3] Checking Ledger suspects...\n";
$sql3 = "SELECT member_id, entry_date, credit, debit, category, COUNT(*) as cnt FROM ledger_entries GROUP BY member_id, entry_date, credit, debit, category HAVING cnt > 2";
$res3 = $conn->query($sql3);
if ($res3 && $res3->num_rows > 0) {
    while($row = $res3->fetch_assoc()) echo "  ⚠️ Suspicious Ledger Cluster: Member #" . $row['member_id'] . " on " . $row['entry_date'] . " (" . $row['category'] . ", Count: " . $row['cnt'] . ")\n";
} else echo "  ✅ clean.\n";

echo "\n--- AUDIT COMPLETE ---\n";
?>
