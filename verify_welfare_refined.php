<?php
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/FinancialEngine.php';

// Ensure $conn exists
if (!isset($conn) || !$conn) {
    die("Error: Database connection not established.\n");
}

$engine = new FinancialEngine($conn);
echo "### WELFARE MODULE INTEGRITY AUDIT ###\n\n";

// 1. Central Pool Reconciliation
$ledger_pool = $engine->getWelfarePoolBalance();

$res = $conn->query("SELECT SUM(amount) as total FROM ledger_transactions WHERE action_type IN ('welfare_contribution', 'welfare_pool_consolidation')");
if (!$res) die("Error querying ledger_transactions: " . $conn->error . "\n");
$total_in = (float)($res->fetch_assoc()['total'] ?? 0);

$res = $conn->query("SELECT SUM(amount) as total FROM ledger_transactions WHERE action_type = 'welfare_payout'");
$total_out = (float)($res->fetch_assoc()['total'] ?? 0);

$calculated_pool = $total_in - $total_out;

echo "1. Pool Balance Check:\n";
echo "   - Transactions IN (Contrib + Consolidation): KES " . number_format($total_in, 2) . "\n";
echo "   - Transactions OUT (Payouts): KES " . number_format($total_out, 2) . "\n";
echo "   - Calculated Pool: KES " . number_format($calculated_pool, 2) . "\n";
echo "   - Ledger Account Balance: KES " . number_format($ledger_pool, 2) . "\n";

if (abs($calculated_pool - $ledger_pool) < 0.01) {
    echo "   [PASS] Pool is perfectly reconciled.\n\n";
} else {
    echo "   [FAIL] Discrepancy of KES " . number_format($ledger_pool - $calculated_pool, 2) . " detected.\n\n";
}

// 2. Case Totals vs Support/Donations
echo "2. Case Financial Integrity:\n";
$cases = $conn->query("SELECT * FROM welfare_cases");
while($c = $cases->fetch_assoc()) {
    $cid = $c['case_id'];
    
    // Raised
    $res = $conn->query("SELECT SUM(amount) as total FROM welfare_donations WHERE case_id = $cid");
    $actual_raised = (float)($res->fetch_assoc()['total'] ?? 0);
    
    // Disbursed
    $res = $conn->query("SELECT SUM(amount) as total FROM welfare_support WHERE case_id = $cid AND status IN ('disbursed', 'approved')");
    $actual_disbursed = (float)($res->fetch_assoc()['total'] ?? 0);
    
    echo "   - Case #$cid ({$c['title']}):\n";
    echo "     - Schema Raised: " . $c['total_raised'] . " | Actual: $actual_raised " . (($c['total_raised'] == $actual_raised) ? "[OK]" : "[MISMATCH]") . "\n";
    echo "     - Schema Disbursed: " . $c['total_disbursed'] . " | Actual: $actual_disbursed " . (($c['total_disbursed'] == $actual_disbursed) ? "[OK]" : "[MISMATCH]") . "\n";
}

echo "\n### AUDIT COMPLETE ###\n";
?>
