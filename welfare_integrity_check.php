<?php
// welfare_integrity_check.php
require 'config/db_connect.php';
require 'inc/FinancialEngine.php';

$engine = new FinancialEngine($conn);
$pool = $engine->getWelfarePoolBalance();

echo "Welfare Pool Balance: KES " . number_format($pool, 2) . "\n";

// Check if pool matches ledger sum
$res = $conn->query("SELECT SUM(credit - debit) as total FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id WHERE la.account_name = 'Welfare Fund Pool'");
$ledger_sum = $res->fetch_assoc()['total'] ?? 0;
echo "Ledger Pool Sum: KES " . number_format($ledger_sum, 2) . "\n";

if ($pool == $ledger_sum) {
    echo "[PASS] Pool balance matches ledger records.\n";
} else {
    echo "[FAIL] Discrepancy detected between engine balance and ledger sum!\n";
}

// Check for pending cases
$res = $conn->query("SELECT COUNT(*) as cnt FROM welfare_cases WHERE status = 'pending'");
echo "Pending Cases: " . $res->fetch_assoc()['cnt'] . "\n";

// Check for disbursed totals
$res = $conn->query("SELECT SUM(amount) as total FROM welfare_support WHERE status = 'disbursed'");
$support_total = $res->fetch_assoc()['total'] ?? 0;
echo "Total Payouts (Support Table): KES " . number_format($support_total, 2) . "\n";
