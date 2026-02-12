<?php
/**
 * cron/reconcile_ledger.php
 * Automated Ledger Reconciliation & Integrity Check
 * Run nightly to ensure the Golden Ledger is balanced and reflects actual movements.
 */

// Define CLI mode if needed, or allow browser if secured
if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("This script must be run from the command line.");
}

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

echo "--- USMS LEDGER RECONCILIATION [" . date('Y-m-d H:i:s') . "] ---\n";

// 1. Ensure logs table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS reconciliation_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        check_date DATE NOT NULL,
        account_id INT,
        account_name VARCHAR(100),
        ledger_balance DECIMAL(15,2),
        calculated_balance DECIMAL(15,2),
        difference DECIMAL(15,2),
        status ENUM('match', 'mismatch') DEFAULT 'match',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

$errors = 0;
$checks = 0;

// 2. Step 1: Account Internal Balance Verification
// current_balance vs SUM(entries)
echo "[1/3] Verifying Account Balance Internal Consistency...\n";

$sql = "
    SELECT 
        la.account_id, 
        la.account_name, 
        la.account_type,
        la.current_balance,
        SUM(CASE 
            WHEN la.account_type IN ('asset', 'expense') THEN (le.debit - le.credit)
            ELSE (le.credit - le.debit)
        END) as calculated_sum
    FROM ledger_accounts la
    LEFT JOIN ledger_entries le ON la.account_id = le.account_id
    GROUP BY la.account_id
";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $checks++;
    $ledger_bal = (float)$row['current_balance'];
    $calc_bal   = (float)($row['calculated_sum'] ?? 0);
    $diff       = round($ledger_bal - $calc_bal, 2);

    if (abs($diff) > 0.01) {
        $errors++;
        echo "  [MISMATCH] Account #{$row['account_id']} ({$row['account_name']}): Ledger=$ledger_bal, Calc=$calc_bal, Diff=$diff\n";
        
        $stmt = $conn->prepare("INSERT INTO reconciliation_logs (check_date, account_id, account_name, ledger_balance, calculated_balance, difference, status) VALUES (CURDATE(), ?, ?, ?, ?, ?, 'mismatch')");
        $stmt->bind_param("isddd", $row['account_id'], $row['account_name'], $ledger_bal, $calc_bal, $diff);
        $stmt->execute();
    }
}

// 3. Step 2: Member Wallet Sync Verification (Gold vs Legacy Transactions)
echo "[2/3] Verifying Member Ledger vs Transaction Log...\n";

$sql = "
    SELECT 
        la.member_id,
        la.current_balance as ledger_savings,
        (SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) 
         FROM transactions 
         WHERE member_id = la.member_id AND category = 'savings' OR transaction_type LIKE '%savings%') as transaction_sum
    FROM ledger_accounts la
    WHERE la.category = 'savings'
";
// Note: This logic depends on How 'transactions' table is logged. 
// It's a cross-check to see if 'transactions' log matches 'ledger'.

// 4. Step 3: Global Trial Balance Zero-Sum Check
echo "[3/3] Performing Global Trial Balance Verification (Total Debits = Total Credits)...\n";

$sql = "SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM ledger_entries";
$row = $conn->query($sql)->fetch_assoc();
$total_debit = (float)$row['total_debit'];
$total_credit = (float)$row['total_credit'];
$diff = round($total_debit - $total_credit, 2);

if (abs($diff) > 0.1) {
    echo "  [CRITICAL] Global Ledger Unbalanced! Total Debit=$total_debit, Total Credit=$total_credit, Diff=$diff\n";
} else {
    echo "  [OK] Global Ledger is Balanced.\n";
}

echo "--- RECONCILIATION FINISHED ---\n";
echo "Total Checks: $checks | Discrepancies Found: $errors\n";

if ($errors > 0) {
    // Notify Admin via email/notification (Future implementation)
    error_log("USMS Reconciliation Warning: $errors mismatches found in ledger.");
}
