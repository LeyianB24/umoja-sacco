<?php
// verify_balance_fix.php
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/FinancialEngine.php';
require_once __DIR__ . '/inc/TransactionHelper.php';

header('Content-Type: text/plain');

echo "========================================================\n";
echo "ONE-TIME LEDGER SYNC - " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

$engine = new FinancialEngine($conn);

// 1. Fetch all members with legacy balances > 0
$members = $conn->query("SELECT member_id, full_name, account_balance FROM members WHERE account_balance > 0");

$count = 0;
$total_synced = 0;

while ($m = $members->fetch_assoc()) {
    $mid = $m['member_id'];
    $legacy_bal = (float)$m['account_balance'];

    // Check current Ledger Balance
    $bals = $engine->getBalances($mid);
    $ledger_bal = (float)$bals['wallet'];

    $diff = round($legacy_bal - $ledger_bal, 2);

    if ($diff > 0) {
        // Missing in Ledger! Add as Opening Balance
        echo "Syncing User: {$m['full_name']} (ID: $mid)... Missing: $diff\n";
        
        try {
            $engine->transact([
                'member_id'   => $mid,
                'amount'      => $diff, 
                'action_type' => 'opening_balance',
                'source_cat'  => 'wallet', 
                'reference'   => 'MIGRATE-V28-' . time(),
                'notes'       => 'Migration: Syncing Legacy Wallet Balance'
            ]);
            $count++;
            $total_synced += $diff;
            echo " -> DONE.\n";
        } catch (Exception $e) {
            echo " -> FAILED: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n--------------------------------------------------------\n";
echo "Synced $count members. Total Value: KES " . number_format($total_synced, 2) . "\n";
echo "Run audit_financials.php to verify.\n";
