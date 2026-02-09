<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');

echo "--- Data Integrity Report ---\n";

// 1. Transactions without valid Related Entities
$res = $conn->query("SELECT COUNT(*) as orphans FROM transactions WHERE related_table='investments' AND related_id NOT IN (SELECT investment_id FROM investments)");
echo "Orphan Investment Transactions: " . $res->fetch_assoc()['orphans'] . "\n";

$res = $conn->query("SELECT COUNT(*) as orphans FROM transactions WHERE related_table='vehicles' AND related_id NOT IN (SELECT vehicle_id FROM vehicles)");
echo "Orphan Vehicle Transactions: " . $res->fetch_assoc()['orphans'] . "\n";

// 2. Missing Ledger Accounts
$res = $conn->query("SELECT COUNT(*) as missing FROM transactions t LEFT JOIN ledger_entries le ON t.transaction_id = le.transaction_id WHERE le.entry_id IS NULL AND t.transaction_type NOT IN ('mpesa_deposit')");
echo "Transactions missing Ledger entries: " . $res->fetch_assoc()['missing'] . "\n";

// 3. Investment Categories Usage
$res = $conn->query("SELECT category, COUNT(*) as count FROM investments GROUP BY category");
echo "\n--- Investment Category Distribution ---\n";
while($row = $res->fetch_assoc()) {
    echo "{$row['category']}: {$row['count']}\n";
}

// 4. Check for double columns
echo "\n--- Fleet/Vehicle Consistency ---\n";
$res = $conn->query("SELECT i.title, v.reg_no FROM investments i JOIN vehicles v ON v.investment_id = i.investment_id");
echo "Linked Vehicles found: " . $res->num_rows . "\n";
?>
