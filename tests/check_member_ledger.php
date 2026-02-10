<?php
// Check ledger accounts for member ID 8 (bezaleltomaka@gmail.com)
require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;

echo "Checking ledger accounts for member ID: $member_id\n\n";

// Check existing accounts
$sql = "SELECT account_id, category, current_balance, account_type 
        FROM ledger_accounts 
        WHERE member_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Existing ledger accounts:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - Category: {$row['category']}, Balance: {$row['current_balance']}, Type: {$row['account_type']}\n";
    }
} else {
    echo "  ⚠️  NO LEDGER ACCOUNTS FOUND!\n";
}

// Check contributions
echo "\nChecking contributions:\n";
$contrib_sql = "SELECT contribution_type, SUM(amount) as total, COUNT(*) as count
                FROM contributions 
                WHERE member_id = ? AND status = 'active'
                GROUP BY contribution_type";
$stmt2 = $conn->prepare($contrib_sql);
$stmt2->bind_param("i", $member_id);
$stmt2->execute();
$contrib_result = $stmt2->get_result();

if ($contrib_result->num_rows > 0) {
    while ($row = $contrib_result->fetch_assoc()) {
        echo "  - Type: {$row['contribution_type']}, Total: KES {$row['total']}, Count: {$row['count']}\n";
    }
} else {
    echo "  No contributions found\n";
}

// Check transactions
echo "\nChecking transactions:\n";
$txn_sql = "SELECT transaction_type, COUNT(*) as count, SUM(amount) as total
            FROM transactions 
            WHERE member_id = ?
            GROUP BY transaction_type";
$stmt3 = $conn->prepare($txn_sql);
$stmt3->bind_param("i", $member_id);
$stmt3->execute();
$txn_result = $stmt3->get_result();

if ($txn_result->num_rows > 0) {
    while ($row = $txn_result->fetch_assoc()) {
        echo "  - Type: {$row['transaction_type']}, Count: {$row['count']}, Total: KES {$row['total']}\n";
    }
} else {
    echo "  No transactions found\n";
}

echo "\n";
