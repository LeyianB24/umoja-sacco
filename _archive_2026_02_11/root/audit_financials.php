<?php
// audit_financials.php
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/FinancialEngine.php';

header('Content-Type: text/plain');

echo "========================================================\n";
echo "FINANCIAL SYSTEM AUDIT - " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

$engine = new FinancialEngine($conn);

// 1. Fetch all members
$members = $conn->query("SELECT member_id, full_name, account_balance FROM members");

echo str_pad("Member", 20) . " | " . str_pad("Legacy Bal", 15) . " | " . str_pad("Wallet Ledger", 15) . " | " . str_pad("Diff", 10) . "\n";
echo str_repeat("-", 70) . "\n";

$total_diff = 0;

while ($m = $members->fetch_assoc()) {
    $mid = $m['member_id'];
    $name = substr($m['full_name'], 0, 18);
    $legacy_bal = (float)$m['account_balance'];

    // Get Ledger Balance for Wallet
    $bals = $engine->getBalances($mid);
    $ledger_bal = (float)$bals['wallet'];

    $diff = round($legacy_bal - $ledger_bal, 2);
    
    if (abs($diff) > 0.01) {
        $total_diff += abs($diff);
        echo str_pad($name, 20) . " | " . 
             str_pad(number_format($legacy_bal, 2), 15) . " | " . 
             str_pad(number_format($ledger_bal, 2), 15) . " | " . 
             str_pad(number_format($diff, 2), 10) . " <--- MISMATCH\n";
    }
}

echo "\n--------------------------------------------------------\n";
echo "Total Discrepancy Amount: " . number_format($total_diff, 2) . "\n";

if ($total_diff == 0) {
    echo "SUCCESS: All Wallet Balances match Ledger!\n";
} else {
    echo "WARNING: Significant discrepancies found. The Dashboard uses 'Wallet Ledger'.\n";
    echo "This explains why balances might appear wrong if Transactions were not recorded in Ledger.\n";
}

echo "\n\n";
echo "========================================================\n";
echo "TRANSACTION TYPE AUDIT (Last 20 Logged in Output)\n";
echo "========================================================\n";

// Check distinct transaction types in transactions vs ledger
$res = $conn->query("SELECT transaction_type, COUNT(*) as cnt FROM transactions GROUP BY transaction_type");
echo "Legacy Transaction Types:\n";
while($row = $res->fetch_assoc()) {
    echo " - " . $row['transaction_type'] . ": " . $row['cnt'] . "\n";
}

echo "\nLedger Account Categories (Active):\n";
$res = $conn->query("SELECT category, COUNT(*) as cnt FROM ledger_accounts GROUP BY category");
while($row = $res->fetch_assoc()) {
    echo " - " . $row['category'] . ": " . $row['cnt'] . "\n";
}

echo "\nAudit Complete.\n";
