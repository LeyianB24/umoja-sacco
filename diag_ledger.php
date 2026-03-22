<?php
require 'config/app.php';
$res = $conn->query('SELECT account_id, account_name, account_type, category, current_balance FROM ledger_accounts');
$rows = [];
while($row = $res->fetch_assoc()) $rows[] = $row;
file_put_contents('ledger_dump.json', json_encode($rows, JSON_PRETTY_PRINT));
echo "Dumped " . count($rows) . " accounts to ledger_dump.json\n";
