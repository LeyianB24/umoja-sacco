<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');

echo "--- CHECKING callback_logs ---\n";
$res1 = $conn->query("SELECT mpesa_receipt_number, COUNT(*) as cnt FROM callback_logs WHERE processed=1 AND mpesa_receipt_number != '' GROUP BY mpesa_receipt_number HAVING cnt > 1");
while($row = $res1->fetch_assoc()) echo "DUPE RECEIPT: " . $row['mpesa_receipt_number'] . " (" . $row['cnt'] . ")\n";

echo "\n--- CHECKING contributions ---\n";
$res2 = $conn->query("SELECT reference_no, COUNT(*) as cnt FROM contributions WHERE status='active' AND reference_no != '' GROUP BY reference_no HAVING cnt > 1");
while($row = $res2->fetch_assoc()) echo "DUPE CONTRIB: " . $row['reference_no'] . " (" . $row['cnt'] . ")\n";

echo "\n--- CHECKING transactions ---\n";
// Some systems use 'transactions', some use 'transaction_records', some use 'ledger_entries'.
// We previously found some in 'transactions'
$res3 = $conn->query("SELECT reference_no, COUNT(*) as cnt FROM transactions WHERE reference_no != '' GROUP BY reference_no HAVING cnt > 1");
while($row = $res3->fetch_assoc()) echo "DUPE TX: " . $row['reference_no'] . " (" . $row['cnt'] . ")\n";

echo "\n--- SYSTEM AUDIT SYNC ---\n";
?>
