<?php
require_once 'config/app.php';
$sql = "SELECT transaction_id, SUM(debit) as d, SUM(credit) as c 
        FROM ledger_entries 
        GROUP BY transaction_id 
        HAVING ABS(SUM(debit) - SUM(credit)) > 0.001";
$res = $conn->query($sql);
$rows = [];
while ($row = $res->fetch_assoc()) {
    $row['diff'] = (float)$row['d'] - (float)$row['c'];
    $rows[] = $row;
}
file_put_contents('imbalanced_txns.json', json_encode($rows, JSON_PRETTY_PRINT));
echo "Found " . count($rows) . " imbalanced transactions.\n";
