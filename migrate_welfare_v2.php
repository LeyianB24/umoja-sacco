<?php
// migrate_welfare_v2.php
require 'config/db_connect.php';

echo "Starting Welfare Migration V2...\n";

// 1. Update welfare_cases table
$sql = "ALTER TABLE welfare_cases 
        ADD COLUMN IF NOT EXISTS requested_amount DECIMAL(15,2) DEFAULT 0.00 AFTER description,
        ADD COLUMN IF NOT EXISTS approved_amount DECIMAL(15,2) DEFAULT 0.00 AFTER requested_amount,
        ADD COLUMN IF NOT EXISTS total_raised DECIMAL(15,2) DEFAULT 0.00 AFTER approved_amount,
        ADD COLUMN IF NOT EXISTS total_disbursed DECIMAL(15,2) DEFAULT 0.00 AFTER total_raised,
        MODIFY COLUMN status ENUM('pending', 'approved', 'funded', 'closed') DEFAULT 'pending'";

if ($conn->query($sql)) {
    echo "- welfare_cases table updated successfully.\n";
} else {
    echo "- Error updating welfare_cases: " . $conn->error . "\n";
}

// 2. Add case_id to welfare_support if missing
$sql = "ALTER TABLE welfare_support ADD COLUMN IF NOT EXISTS case_id INT NULL AFTER amount";
$conn->query($sql);

// 3. Ensure System Ledger Accounts
$system_accounts = [
    ['Welfare Fund Pool', 'liability', 'welfare'],
    ['Welfare Disbursement Expense', 'expense', 'welfare']
];

foreach ($system_accounts as $acc) {
    $check = $conn->prepare("SELECT account_id FROM ledger_accounts WHERE account_name = ?");
    $check->bind_param("s", $acc[0]);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO ledger_accounts (account_name, account_type, category, current_balance) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("sss", $acc[0], $acc[1], $acc[2]);
        $stmt->execute();
        echo "- Ledger Account '{$acc[0]}' created.\n";
    }
}

echo "Migration Complete.\n";
