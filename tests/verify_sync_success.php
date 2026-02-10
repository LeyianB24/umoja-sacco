<?php
// Verify ledger entries were created
require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;

echo "VERIFICATION CHECK - Member ID: $member_id\n";
echo str_repeat("=", 50) . "\n\n";

// 1. Check ledger entries count
$entry_count_sql = "SELECT COUNT(*) as cnt 
                    FROM ledger_entries le
                    JOIN ledger_accounts la ON le.account_id = la.account_id
                    WHERE la.member_id = ?";
$stmt = $conn->prepare($entry_count_sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$entry_count = $stmt->get_result()->fetch_assoc()['cnt'];

echo "1. Ledger Entries Created: $entry_count\n";

// 2. Check ledger balances
echo "\n2. Ledger Account Balances:\n";
$balance_sql = "SELECT category, current_balance 
                FROM ledger_accounts 
                WHERE member_id = ?
                ORDER BY category";
$stmt2 = $conn->prepare($balance_sql);
$stmt2->bind_param("i", $member_id);
$stmt2->execute();
$result = $stmt2->get_result();

$total = 0;
while ($row = $result->fetch_assoc()) {
    $balance = (float)$row['current_balance'];
    $total += $balance;
    echo "   " . ucfirst($row['category']) . ": KES " . number_format($balance, 2) . "\n";
}

echo "\n3. Total Balance: KES " . number_format($total, 2) . "\n";

// 3. Compare with contributions
echo "\n4. Contribution Totals (for comparison):\n";
$contrib_sql = "SELECT contribution_type, SUM(amount) as total
                FROM contributions 
                WHERE member_id = ? AND status = 'active'
                GROUP BY contribution_type";
$stmt3 = $conn->prepare($contrib_sql);
$stmt3->bind_param("i", $member_id);
$stmt3->execute();
$contrib_result = $stmt3->get_result();

while ($row = $contrib_result->fetch_assoc()) {
    echo "   " . ucfirst($row['contribution_type']) . ": KES " . number_format($row['total'], 2) . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

if ($entry_count > 0 && $total > 0) {
    echo "✅ SUCCESS! Ledger is synced and balances are correct.\n";
    echo "🔄 Please HARD REFRESH your browser (Ctrl+Shift+R)\n";
} else {
    echo "❌ ISSUE: Ledger still has problems.\n";
}
