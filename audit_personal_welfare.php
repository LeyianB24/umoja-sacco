<?php
require 'c:\xampp\htdocs\usms\config\db_connect.php';
require 'c:\xampp\htdocs\usms\inc\FinancialEngine.php';

$engine = new FinancialEngine($conn);
$pool = $engine->getWelfarePoolBalance();
echo "Central Welfare Pool Balance: KES " . number_format($pool, 2) . "\n\n";

$res = $conn->query("SELECT m.full_name, la.current_balance, la.account_id 
                   FROM ledger_accounts la 
                   JOIN members m ON la.member_id = m.member_id 
                   WHERE la.category = 'welfare' AND la.current_balance > 0");

if ($res->num_rows > 0) {
    echo "--- Personal Welfare Balances Found ---\n";
    while($row = $res->fetch_assoc()) {
        echo "Member: {$row['full_name']} | Bal: {$row['current_balance']} (ID: {$row['account_id']})\n";
    }
} else {
    echo "No personal welfare balances found. All funds likely pooled.\n";
}
?>
