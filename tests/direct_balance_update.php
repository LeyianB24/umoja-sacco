<?php
/**
 * Direct SQL Update of Ledger Balances
 */
require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;

echo "=== Direct Ledger Balance Update ===\n\n";

// Step 1: Get contribution totals
$contrib_sql = "SELECT 
    SUM(CASE WHEN contribution_type = 'savings' THEN amount ELSE 0 END) as savings_total,
    SUM(CASE WHEN contribution_type = 'shares' THEN amount ELSE 0 END) as shares_total,
    SUM(CASE WHEN contribution_type = 'welfare' THEN amount ELSE 0 END) as welfare_total
FROM contributions 
WHERE member_id = ? AND status = 'active'";

$stmt = $conn->prepare($contrib_sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$totals = $result->fetch_assoc();

echo "Calculated Totals from Contributions:\n";
echo "  Savings: KES " . number_format($totals['savings_total'], 2) . "\n";
echo "  Shares: KES " . number_format($totals['shares_total'], 2) . "\n";
echo "  Welfare: KES " . number_format($totals['welfare_total'], 2) . "\n\n";

// Step 2: Update ledger accounts directly
echo "Updating Ledger Accounts:\n";

$updates = [
    'savings' => $totals['savings_total'],
    'shares' => $totals['shares_total'],
    'welfare' => $totals['welfare_total']
];

foreach ($updates as $category => $amount) {
    if ($amount > 0) {
        $update_sql = "UPDATE ledger_accounts 
                       SET current_balance = ? 
                       WHERE member_id = ? AND category = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dis", $amount, $member_id, $category);
        
        if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
            echo "  ✅ Updated $category to KES " . number_format($amount, 2) . "\n";
        } else {
            echo "  ⚠️  No rows affected for $category (may not exist)\n";
        }
    }
}

// Step 3: Verify
echo "\nVerifying Final Balances:\n";
$verify_sql = "SELECT category, current_balance FROM ledger_accounts WHERE member_id = ? ORDER BY category";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("i", $member_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

$grand_total = 0;
while ($row = $verify_result->fetch_assoc()) {
    $balance = (float)$row['current_balance'];
    $grand_total += $balance;
    echo "  " . ucfirst($row['category']) . ": KES " . number_format($balance, 2) . "\n";
}

echo "\nGrand Total: KES " . number_format($grand_total, 2) . "\n";
echo "\n✅ Update Complete! Please refresh your dashboard.\n";
