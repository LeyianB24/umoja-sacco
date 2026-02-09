<?php
require_once __DIR__ . '/config/db_connect.php';

file_put_contents('audit_report.txt', '');

function log_line($text) {
    file_put_contents('audit_report.txt', $text . "\n", FILE_APPEND);
    echo $text . "\n";
}

log_line("═══════════════════════════════════════════════════════════════");
log_line("  DATABASE ARCHITECTURE AUDIT - " . date('Y-m-d H:i:s'));
log_line("═══════════════════════════════════════════════════════════════");
log_line("");

// 1. Orphaned Revenue
$orphan_rev = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

log_line("1. ORPHANED REVENUE");
log_line("   Count: " . $orphan_rev['count']);
if ($orphan_rev['count'] > 0) {
    log_line("   Status: ❌ CRITICAL");
    $samples = $conn->query("
        SELECT transaction_id, amount, transaction_date, notes
        FROM transactions 
        WHERE transaction_type IN ('income', 'revenue_inflow') 
        AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
        LIMIT 5
    ");
    log_line("   Sample records:");
    while ($s = $samples->fetch_assoc()) {
        log_line("     - ID: {$s['transaction_id']}, Amount: {$s['amount']}, Date: {$s['transaction_date']}");
    }
} else {
    log_line("   Status: ✓ PASS");
}
log_line("");

// 2. Orphaned Expenses
$orphan_exp = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

log_line("2. ORPHANED EXPENSES");
log_line("   Count: " . $orphan_exp['count']);
if ($orphan_exp['count'] > 0) {
    log_line("   Status: ❌ CRITICAL");
    $samples = $conn->query("
        SELECT transaction_id, amount, transaction_date, notes
        FROM transactions 
        WHERE transaction_type IN ('expense', 'expense_outflow') 
        AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
        LIMIT 5
    ");
    log_line("   Sample records:");
    while ($s = $samples->fetch_assoc()) {
        log_line("     - ID: {$s['transaction_id']}, Amount: {$s['amount']}, Date: {$s['transaction_date']}");
    }
} else {
    log_line("   Status: ✓ PASS");
}
log_line("");

// 3. Revenue Distribution
log_line("3. REVENUE DISTRIBUTION BY SOURCE");
$rev_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow')
    GROUP BY related_table
");
while ($row = $rev_dist->fetch_assoc()) {
    $table = $row['related_table'] ?: 'NULL';
    log_line(sprintf("   %-20s: %5d records | KES %s", $table, $row['count'], number_format($row['total'])));
}
log_line("");

// 4. Expense Distribution
log_line("4. EXPENSE DISTRIBUTION BY SOURCE");
$exp_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow')
    GROUP BY related_table
");
while ($row = $exp_dist->fetch_assoc()) {
    $table = $row['related_table'] ?: 'NULL';
    log_line(sprintf("   %-20s: %5d records | KES %s", $table, $row['count'], number_format($row['total'])));
}
log_line("");

// 5. Investments without targets
$no_target = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0")->fetch_assoc();
log_line("5. INVESTMENTS WITHOUT TARGETS");
log_line("   Count: " . $no_target['c']);
if ($no_target['c'] > 0) {
    log_line("   Status: ⚠️  WARNING");
} else {
    log_line("   Status: ✓ PASS");
}
log_line("");

// 6. Summary
log_line("═══════════════════════════════════════════════════════════════");
log_line("  SUMMARY");
log_line("═══════════════════════════════════════════════════════════════");

$critical_issues = 0;
$warnings = 0;

if ($orphan_rev['count'] > 0) {
    log_line("❌ CRITICAL: {$orphan_rev['count']} revenue records not linked to investments");
    $critical_issues++;
}

if ($orphan_exp['count'] > 0) {
    log_line("❌ CRITICAL: {$orphan_exp['count']} expense records not linked to investments");
    $critical_issues++;
}

if ($no_target['c'] > 0) {
    log_line("⚠️  WARNING: {$no_target['c']} investments lack performance targets");
    $warnings++;
}

if ($critical_issues == 0 && $warnings == 0) {
    log_line("✅ ALL CHECKS PASSED - Architecture is sound");
}

log_line("");
log_line("Report saved to: audit_report.txt");
log_line("═══════════════════════════════════════════════════════════════");
