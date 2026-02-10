<?php
/**
 * Manual Ledger Balance Update
 * Directly updates ledger_accounts based on contributions
 */

require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;

echo "=== Manual Ledger Balance Update ===\n\n";

// Get contribution totals
$sql = "SELECT contribution_type, SUM(amount) as total
        FROM contributions 
        WHERE member_id = ? AND status = 'active'
        GROUP BY contribution_type";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$totals = [];
while ($row = $result->fetch_assoc()) {
    $totals[$row['contribution_type']] = (float)$row['total'];
}

echo "Contribution Totals:\n";
foreach ($totals as $type => $amount) {
    echo "  - $type: KES " . number_format($amount, 2) . "\n";
}

// Update ledger accounts
echo "\nUpdating ledger accounts:\n";

foreach ($totals as $type => $amount) {
    // Map contribution type to ledger category
    $category = match($type) {
        'shares' => 'shares',
        'welfare' => 'welfare',
        'savings' => 'savings',
        default => 'savings'
    };
    
    // Update the ledger account
    $update_sql = "UPDATE ledger_accounts 
                   SET current_balance = ? 
                   WHERE member_id = ? AND category = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("dis", $amount, $member_id, $category);
    
    if ($update_stmt->execute()) {
        echo "  ✅ Updated $category: KES " . number_format($amount, 2) . "\n";
    } else {
        echo "  ❌ Failed to update $category\n";
    }
}

// Verify final balances
echo "\nFinal Balances:\n";
$verify_sql = "SELECT category, current_balance 
               FROM ledger_accounts 
               WHERE member_id = ?";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("i", $member_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

while ($row = $verify_result->fetch_assoc()) {
    echo "  - {$row['category']}: KES " . number_format($row['current_balance'], 2) . "\n";
}

echo "\n✅ Manual update complete!\n";
