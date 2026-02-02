<?php
require_once 'config/db_connect.php';
$q = $conn->query("SELECT account_name, category FROM ledger_accounts WHERE account_name IN ('Cash at Hand', 'M-Pesa Float', 'Bank Account')");
while($row = $q->fetch_assoc()) {
    echo "{$row['account_name']} | Category: " . ($row['category'] ?? 'NULL') . "\n";
}
