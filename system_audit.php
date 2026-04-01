<?php
/**
 * system_audit.php
 * Comprehensive System Health & Integrity Audit Tool
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/inc/SystemHealthHelper.php';

echo "=== USMS SYSTEM HEALTH AUDIT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// $conn is already provided by config/app.php
global $conn;
if (!$conn || $conn->connect_error) {
    die("❌ DATABASE CONNECTION FAILED: " . ($conn->connect_error ?? 'Not initialized') . "\n");
}

function print_check($title, $status, $details = "") {
    $icon = $status ? "✅" : "❌";
    printf("%-2s %-30s: %s\n", $icon, $title, ($status ? "PASS" : "FAIL"));
    if ($details) echo "   ℹ️  $details\n";
}

// 1. Database Schema Checks
echo "--- [1] Schema Integrity ---\n";
$tables = ['members', 'loans', 'ledger_accounts', 'ledger_entries', 'ledger_transactions', 'transactions'];
foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    print_check("Table: $table", $res->num_rows > 0);
}

// Check for critical columns
$res = $conn->query("SHOW COLUMNS FROM loans LIKE 'current_balance'");
print_check("Column: loans.current_balance", $res->num_rows > 0);

$res = $conn->query("SHOW COLUMNS FROM members LIKE '_deprecated_account_balance'");
print_check("Column: members._deprecated_account_balance", $res->num_rows > 0);

echo "\n--- [2] Ledger Integrity ---\n";
$health = getSystemHealth($conn);
print_check("Ledger Balance", !$health['ledger_imbalance'], $health['ledger_imbalance'] ? "Imbalance detected in total debit vs credit" : "Total Debits match Total Credits");

// Check for system accounts
$sys_accounts = ['SACCO Revenue', 'SACCO Expenses', 'Welfare Fund Pool', 'Paystack Clearing Account'];
foreach ($sys_accounts as $acc) {
    $res = $conn->query("SELECT account_id FROM ledger_accounts WHERE account_name = '$acc'");
    print_check("System Account: $acc", $res->num_rows > 0);
}

echo "\n--- [3] Operations Health ---\n";
print_check("Pending Trans (>5m)", $health['pending_transactions'] == 0, $health['pending_transactions'] . " requests stalled");
print_check("Failed Callbacks (Today)", $health['failed_callbacks'] == 0, $health['failed_callbacks'] . " failures logged");
print_check("Callback Success Rate", $health['callback_success_rate'] >= 95, $health['callback_success_rate'] . "%");

echo "\n--- [4] System Resources ---\n";
echo "📦 Database Size: " . $health['db_size'] . " MB\n";

echo "\n--- [5] Critical Log Audit ---\n";
// Check for last 5 errors in temp or logic logs if available
if (file_exists('error_log')) {
    $errors = shell_exec('tail -n 5 error_log');
    echo "Recent Errors:\n$errors\n";
} else {
    echo "No local error_log found.\n";
}

echo "\n=== AUDIT COMPLETE ===\n";
if ($health['ledger_imbalance']) {
    echo "⚠️  WARNING: Ledger imbalance requires immediate attention! Run rebalance_ledger.php if needed.\n";
}
