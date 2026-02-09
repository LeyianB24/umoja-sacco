<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$out = "--- Data Integrity Report ---\n";
$res = $conn->query("SELECT COUNT(*) as orphans FROM transactions WHERE related_table='investments' AND related_id > 0 AND related_id NOT IN (SELECT investment_id FROM investments)");
$out .= "Orphan Investment Transactions: " . $res->fetch_assoc()['orphans'] . "\n";
$res = $conn->query("SELECT COUNT(*) as orphans FROM transactions WHERE related_table='vehicles' AND related_id > 0 AND related_id NOT IN (SELECT vehicle_id FROM vehicles)");
$out .= "Orphan Vehicle Transactions: " . $res->fetch_assoc()['orphans'] . "\n";
$res = $conn->query("SELECT COUNT(*) as missing FROM transactions t LEFT JOIN ledger_entries le ON t.transaction_id = le.transaction_id WHERE le.entry_id IS NULL AND t.transaction_type NOT IN ('mpesa_deposit')");
$out .= "Transactions missing Ledger entries: " . $res->fetch_assoc()['missing'] . "\n";
$res = $conn->query("SELECT category, COUNT(*) as count FROM investments GROUP BY category");
$out .= "\n--- Investment Category Distribution ---\n";
while($row = $res->fetch_assoc()) {
    $out .= "{$row['category']}: {$row['count']}\n";
}
$res = $conn->query("SELECT i.title, v.reg_no FROM investments i JOIN vehicles v ON v.investment_id = i.investment_id");
$out .= "\nLinked Vehicles found: " . $res->num_rows . "\n";
file_put_contents('final_integrity_report.txt', $out);
?>
