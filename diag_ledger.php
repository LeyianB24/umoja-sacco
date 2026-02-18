<?php
require_once __DIR__ . '/config/db_connect.php';

echo "--- Ledger Categories ---\n";
$res = $conn->query("SELECT category, COUNT(*) as count FROM ledger_accounts GROUP BY category");
while($row = $res->fetch_assoc()) {
    printf("Category: [%s], Count: %d\n", $row['category'], $row['count']);
}

echo "\n--- All Wallet Balances ---\n";
$res = $conn->query("SELECT m.full_name, la.current_balance, la.account_id 
                    FROM ledger_accounts la 
                    JOIN members m ON la.member_id = m.member_id 
                    WHERE la.category = 'wallet' 
                    ORDER BY la.current_balance DESC");
while($row = $res->fetch_assoc()) {
    printf("Member: %s, Wallet: %.2f (Acc ID: %d)\n", $row['full_name'], $row['current_balance'], $row['account_id']);
}





