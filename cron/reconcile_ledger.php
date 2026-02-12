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

$do_fix = in_array('--fix', $argv) || isset($_GET['fix']);

// 2. Step 1: Account Internal Balance Verification
// current_balance vs SUM(entries)
echo "[1/4] Verifying Account Balance Internal Consistency...\n";

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
        
        if ($do_fix) {
            echo "    [FIXING] Updating current_balance to $calc_bal...\n";
            $u_stmt = $conn->prepare("UPDATE ledger_accounts SET current_balance = ? WHERE account_id = ?");
            $u_stmt->bind_param("di", $calc_bal, $row['account_id']);
            $u_stmt->execute();
        }

        $stmt = $conn->prepare("INSERT INTO reconciliation_logs (check_date, account_id, account_name, ledger_balance, calculated_balance, difference, status) VALUES (CURDATE(), ?, ?, ?, ?, ?, 'mismatch')");
        $stmt->bind_param("isddd", $row['account_id'], $row['account_name'], $ledger_bal, $calc_bal, $diff);
        $stmt->execute();
    }
}

// 3. Step 2: Global Trial Balance Zero-Sum Check
echo "[2/4] Performing Global Trial Balance Verification (Total Dr = Total Cr)...\n";

$sql = "SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM ledger_entries";
$row = $conn->query($sql)->fetch_assoc();
$total_debit = (float)$row['total_debit'];
$total_credit = (float)$row['total_credit'];
$diff = round($total_debit - $total_credit, 2);

if (abs($diff) > 0.01) {
    echo "  [CRITICAL] Global Ledger Unbalanced! Total Debit=$total_debit, Total Credit=$total_credit, Diff=$diff\n";
    $errors++;
} else {
    echo "  [OK] Global Ledger is Balanced.\n";
}

// 4. Step 3: Individual Transaction Zero-Sum Check
echo "[3/4] Verifying Per-Transaction Balanced State...\n";

$sql = "SELECT transaction_id, SUM(debit) as total_debit, SUM(credit) as total_credit 
        FROM ledger_entries 
        GROUP BY transaction_id 
        HAVING ABS(SUM(debit) - SUM(credit)) > 0.01";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $errors++;
        echo "  [IMBALANCE] Ledger Txn #{$row['transaction_id']}: Dr={$row['total_debit']}, Cr={$row['total_credit']}\n";
    }
} else {
    echo "  [OK] All individual transactions are balanced.\n";
}

// 5. Step 4: Legacy Sync Integrity Check
echo "[4/4] Verifying Legacy 'transactions' Table Mapping...\n";
// Ensure every record in legacy transactions table has a valid ledger_transaction_id mapping
$sql = "SELECT COUNT(*) as c FROM transactions WHERE ledger_transaction_id IS NULL OR ledger_transaction_id = 0";
$missing_mapping = $conn->query($sql)->fetch_assoc()['c'] ?? 0;

if ($missing_mapping > 0) {
    echo "  [WARNING] $missing_mapping records in 'transactions' table lack 'ledger_transaction_id'.\n";
} else {
    echo "  [OK] All legacy transactions are linked to the Golden Ledger.\n";
}

// 6. Step 5: Loan Status Consistency Check
echo "[5/5] Verifying Loan Status Consistency...\n";
$sql = "SELECT loan_id, current_balance, status FROM loans WHERE (current_balance <= 0 AND status != 'completed') OR (current_balance > 0 AND status = 'completed')";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $errors++;
        echo "  [MISMATCH] Loan #{$row['loan_id']}: Balance={$row['current_balance']}, Status='{$row['status']}'\n";
        if ($do_fix) {
            $new_status = ($row['current_balance'] <= 0) ? 'completed' : 'disbursed';
            echo "    [FIXING] Updating status to '$new_status'...\n";
            $conn->query("UPDATE loans SET status = '$new_status' WHERE loan_id = " . $row['loan_id']);
        }
    }
} else {
    echo "  [OK] All loans have consistent statuses.\n";
}

echo "--- RECONCILIATION FINISHED ---\n";
echo "Total Checks: $checks | Critical Discrepancies Found: $errors\n";

if ($errors > 0) {
    error_log("USMS Reconciliation Warning: $errors critical mismatches found in ledger.");
}
