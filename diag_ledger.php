<?php
require_once __DIR__ . '/config/db_connect.php';

$res = $conn->query("SELECT account_id, account_name, account_type, category, current_balance FROM ledger_accounts ORDER BY account_type, account_name");
echo "ID | Account Name | Type | Category | Balance\n";
echo "---|--------------|------|----------|--------\n";
while ($row = $res->fetch_assoc()) {
    printf("%2d | %-20s | %-10s | %-10s | %10.2f\n", 
        $row['account_id'], $row['account_name'], $row['account_type'], $row['category'], $row['current_balance']);
}
