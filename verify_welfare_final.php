<?php
/**
 * verify_welfare_final.php
 * Robust reconciliation of the Welfare Pooled Fund.
 */
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/FinancialEngine.php';

$engine = new FinancialEngine($conn);
echo "\n=== WELFARE MODULE FINAL AUDIT ===\n\n";

// 1. Get Welfare System Account ID
$res = $conn->query("SELECT account_id FROM ledger_accounts WHERE account_name = 'Welfare Fund Pool' LIMIT 1");
if (!$res || $res->num_rows === 0) {
    die("Error: Welfare System Account (Welfare Fund Pool) not found.\n");
}
$pool_acc_id = (int)$res->fetch_assoc()['account_id'];

// 2. Reconcile pool based on ledger entries
$res = $conn->query("SELECT SUM(credit) as total_in, SUM(debit) as total_out FROM ledger_entries WHERE account_id = $pool_acc_id");
$row = $res->fetch_assoc();
$total_in = (float)($row['total_in'] ?? 0);
$total_out = (float)($row['total_out'] ?? 0);
$calculated_pool = $total_in - $total_out;

// 3. Get Engine-reported balance
$engine_pool = $engine->getWelfarePoolBalance();

echo "1. Pool Reconciliation:\n";
echo "   - Total Contributions/Consolidations (Credits): KES " . number_format($total_in, 2) . "\n";
echo "   - Total Payouts (Debits): KES " . number_format($total_out, 2) . "\n";
echo "   - Calculated Pool Balance: KES " . number_format($calculated_pool, 2) . "\n";
echo "   - Engine Reported Balance: KES " . number_format($engine_pool, 2) . "\n";

if (abs($calculated_pool - $engine_pool) < 0.01) {
    echo "   [PASS] Pool is consistent.\n\n";
} else {
    echo "   [FAIL] Inconsistency detected.\n\n";
}

// 4. Case Integrity
echo "2. Case Integrity Check:\n";
$cases = $conn->query("SELECT c.*, m.full_name FROM welfare_cases c LEFT JOIN members m ON c.related_member_id = m.member_id");
while($c = $cases->fetch_assoc()) {
    $cid = $c['case_id'];
    
    // Check donations table
    $res_d = $conn->query("SELECT COALESCE(SUM(amount), 0) as raised FROM welfare_donations WHERE case_id = $cid");
    $actual_raised = (float)$res_d->fetch_assoc()['raised'];
    
    // Check support table
    $res_s = $conn->query("SELECT COALESCE(SUM(amount), 0) as disbursed FROM welfare_support WHERE case_id = $cid AND status = 'disbursed'");
    $actual_disbursed = (float)$res_s->fetch_assoc()['disbursed'];
    
    echo "   - Case #$cid ({$c['title']}) for " . ($c['full_name'] ?? 'General') . ":\n";
    echo "     - Raised: " . number_format($c['total_raised'], 2) . " vs " . number_format($actual_raised, 2) . " " . ($c['total_raised'] == $actual_raised ? "[OK]" : "[MISMATCH]") . "\n";
    echo "     - Disbursed: " . number_format($c['total_disbursed'], 2) . " vs " . number_format($actual_disbursed, 2) . " " . ($c['total_disbursed'] == $actual_disbursed ? "[OK]" : "[MISMATCH]") . "\n";
}

echo "\n=== AUDIT COMPLETE ===\n";
?>
