<?php
require 'config/app.php';
$ids = [232, 243, 244, 245, 246, 247, 248, 257, 258, 259, 260];
$id_list = implode(',', $ids);
$res = $conn->query("SELECT * FROM ledger_transactions WHERE transaction_id IN ($id_list)");
$rows = [];
while($row = $res->fetch_assoc()) {
    $tid = $row['transaction_id'];
    $entries_res = $conn->query("SELECT * FROM ledger_entries WHERE transaction_id = $tid");
    $entries = [];
    while($e = $entries_res->fetch_assoc()) $entries[] = $e;
    $row['entries'] = $entries;
    $rows[] = $row;
}
file_put_contents('imbalanced_txns.json', json_encode($rows, JSON_PRETTY_PRINT));
echo "Dumped " . count($rows) . " imbalanced transactions to imbalanced_txns.json\n";
