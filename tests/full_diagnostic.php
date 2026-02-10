<?php
require_once __DIR__ . '/../config/db_connect.php';

$member_id = 8;

echo "=== COMPREHENSIVE DATABASE CHECK ===\n\n";

// 1. Ledger Accounts
echo "1. LEDGER ACCOUNTS:\n";
$sql = "SELECT * FROM ledger_accounts WHERE member_id = $member_id";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "NO LEDGER ACCOUNTS FOUND!\n";
}

// 2. Contributions
echo "\n2. CONTRIBUTIONS:\n";
$sql = "SELECT * FROM contributions WHERE member_id = $member_id ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['contribution_id']}, Type: {$row['contribution_type']}, Amount: {$row['amount']}, Status: {$row['status']}, Ref: {$row['reference_no']}\n";
    }
} else {
    echo "NO CONTRIBUTIONS FOUND!\n";
}

// 3. Transactions
echo "\n3. TRANSACTIONS:\n";
$sql = "SELECT * FROM transactions WHERE member_id = $member_id ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['transaction_id']}, Type: {$row['transaction_type']}, Amount: {$row['amount']}, Ref: {$row['reference_no']}\n";
    }
} else {
    echo "NO TRANSACTIONS FOUND!\n";
}

// 4. Ledger Transactions
echo "\n4. LEDGER TRANSACTIONS:\n";
$sql = "SELECT lt.*, la.category 
        FROM ledger_transactions lt
        LEFT JOIN ledger_entries le ON lt.transaction_id = le.transaction_id
        LEFT JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE la.member_id = $member_id
        LIMIT 10";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "NO LEDGER TRANSACTIONS FOUND!\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
