<?php
/**
 * migrate_enforcement.php
 * USMS Enforcement Migration Script
 */
require_once __DIR__ . '/config/db_connect.php';

$sql = [
    // 1. Rename legacy balance column
    "ALTER TABLE members CHANGE COLUMN account_balance _deprecated_account_balance DECIMAL(15,2) DEFAULT 0.00",
    
    // 2. Add UNIQUE constraint to ledger_transactions reference_no
    "ALTER TABLE ledger_transactions ADD UNIQUE INDEX idx_unique_ref (reference_no)",
    
    // 3. Add ledger_transaction_id to transactions table if missing
    "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS ledger_transaction_id INT NULL AFTER transaction_id",
    
    // 4. Ensure indexes
    "CREATE INDEX IF NOT EXISTS idx_txn_ref ON transactions(reference_no)",
    "CREATE INDEX IF NOT EXISTS idx_txn_member ON transactions(member_id)",
    "CREATE INDEX IF NOT EXISTS idx_txn_date ON transactions(created_at)",
    "CREATE INDEX IF NOT EXISTS idx_admins_role ON admins(role_id)",
    
    // 5. Create reconciliation_logs table
    "CREATE TABLE IF NOT EXISTS reconciliation_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        ledger_sum DECIMAL(15,2) NOT NULL,
        account_balance DECIMAL(15,2) NOT NULL,
        difference DECIMAL(15,2) NOT NULL,
        status ENUM('flagged', 'resolved') DEFAULT 'flagged',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
];

foreach ($sql as $query) {
    echo "Executing: $query ... ";
    if ($conn->query($query)) {
        echo "OK\n";
    } else {
        echo "FAILED: " . $conn->error . "\n";
    }
}

echo "\nMigration Complete.\n";
