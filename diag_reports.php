<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT account_name, account_type, category FROM ledger_accounts");
echo "NAME | TYPE | CAT\n";
while($row = $res->fetch_assoc()) echo "{$row['account_name']} | {$row['account_type']} | {$row['category']}\n";
