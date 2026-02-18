<?php
require_once __DIR__ . '/config/db_connect.php';

$tid = 354;
echo "--- Transaction #$tid Analysis ---\n";

$res = $conn->query("SELECT * FROM ledger_transactions WHERE transaction_id = $tid");
$txn = $res->fetch_assoc();
print_r($txn);

echo "\nEntries:\n";
$res = $conn->query("SELECT le.*, la.account_name, la.category 
                    FROM ledger_entries le 
                    JOIN ledger_accounts la ON le.account_id = la.account_id 
                    WHERE le.transaction_id = $tid");
while($row = $res->fetch_assoc()) {
    printf("  Acc: [%s] (%s) -> D: %.2f, C: %.2f\n", $row['account_name'], $row['category'], $row['debit'], $row['credit']);
}
