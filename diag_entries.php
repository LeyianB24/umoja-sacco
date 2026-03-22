<?php
require 'config/app.php';
$res = $conn->query('SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM ledger_entries');
$row = $res->fetch_assoc();
echo "Total Debits: " . number_format((float)$row['total_debit'], 2) . "\n";
echo "Total Credits: " . number_format((float)$row['total_credit'], 2) . "\n";
echo "Difference: " . number_format((float)$row['total_debit'] - (float)$row['total_credit'], 2) . "\n";

$res2 = $conn->query('SELECT transaction_id, SUM(debit) as d, SUM(credit) as c FROM ledger_entries GROUP BY transaction_id HAVING ABS(d - c) > 0.01');
$imbalanced_txns = [];
while($r = $res2->fetch_assoc()) $imbalanced_txns[] = $r;
echo "Found " . count($imbalanced_txns) . " imbalanced transactions.\n";
if (!empty($imbalanced_txns)) {
    print_r($imbalanced_txns);
}
