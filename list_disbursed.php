<?php
require_once __DIR__ . '/config/db_connect.php';

$res = $conn->query("DESCRIBE ledger_transactions");
echo "--- Ledger Transactions Columns ---\n";
while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";

echo "\n--- Disbursed Loans & Wallet Balances ---\n";
$res2 = $conn->query("SELECT m.full_name, m.member_id, l.loan_id, l.amount 
                    FROM loans l 
                    JOIN members m ON l.member_id = m.member_id 
                    WHERE l.status='disbursed'");
while($row = $res2->fetch_assoc()) {
    $mid = $row['member_id'];
    $w_res = $conn->query("SELECT current_balance FROM ledger_accounts WHERE member_id = $mid AND category='wallet'");
    $bal = $w_res->fetch_assoc()['current_balance'] ?? 0;
    
    printf("L#%d | %s | Wal: %.2f\n", $row['loan_id'], substr($row['full_name'], 0, 15), $bal);
}

