<?php
// Quick balance check for member 8
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';

$member_id = 8;
$engine = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

echo "Current Balances for Member ID $member_id:\n";
echo "==========================================\n";
foreach ($balances as $category => $amount) {
    echo sprintf("%-10s: KES %s\n", ucfirst($category), number_format($amount, 2));
}
echo "==========================================\n";
echo "Total Assets: KES " . number_format(array_sum($balances), 2) . "\n";
