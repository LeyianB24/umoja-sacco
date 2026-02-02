<?php
require_once 'config/db_connect.php';
$q = $conn->query("SELECT * FROM ledger_accounts");
printf("%-5s | %-25s | %-10s | %-10s | %-15s\n", "ID", "Name", "Type", "Category", "Balance");
echo str_repeat("-", 75) . "\n";
while($row = $q->fetch_assoc()) {
    printf("%-5s | %-25s | %-10s | %-10s | %-15s\n", 
        $row['account_id'], 
        $row['account_name'], 
        $row['account_type'], 
        $row['category'], 
        number_format($row['current_balance'], 2)
    );
}
