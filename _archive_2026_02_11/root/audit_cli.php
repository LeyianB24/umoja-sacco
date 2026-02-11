<?php
/**
 * CLI Database Architecture Audit
 */

require_once __DIR__ . '/config/db_connect.php';

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  DATABASE ARCHITECTURE AUDIT REPORT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Data Integrity - Orphaned Records
echo "1. DATA INTEGRITY CHECK\n";
echo "───────────────────────────────────────────────────────────────\n";

$orphan_rev = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

$orphan_exp = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

echo "Orphaned Revenue Records: " . $orphan_rev['count'] . ($orphan_rev['count'] > 0 ? " ❌" : " ✓") . "\n";
echo "Orphaned Expense Records: " . $orphan_exp['count'] . ($orphan_exp['count'] > 0 ? " ❌" : " ✓") . "\n\n";

// 2. Revenue Distribution
echo "2. REVENUE DISTRIBUTION BY SOURCE\n";
echo "───────────────────────────────────────────────────────────────\n";
$rev_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow')
    GROUP BY related_table
");
while ($row = $rev_dist->fetch_assoc()) {
    $table = $row['related_table'] ?: 'NULL';
    printf("%-20s: %5d records | KES %s\n", $table, $row['count'], number_format($row['total']));
}
echo "\n";

// 3. Expense Distribution
echo "3. EXPENSE DISTRIBUTION BY SOURCE\n";
echo "───────────────────────────────────────────────────────────────\n";
$exp_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow')
    GROUP BY related_table
");
while ($row = $exp_dist->fetch_assoc()) {
    $table = $row['related_table'] ?: 'NULL';
    printf("%-20s: %5d records | KES %s\n", $table, $row['count'], number_format($row['total']));
}
echo "\n";

// 4. Investment Categories
echo "4. INVESTMENT CATEGORIES (Data-Driven)\n";
echo "───────────────────────────────────────────────────────────────\n";
$categories = $conn->query("SELECT category, COUNT(*) as c FROM investments GROUP BY category ORDER BY category");
while ($cat = $categories->fetch_assoc()) {
    printf("%-20s: %d assets\n", $cat['category'], $cat['c']);
}
echo "\n";

// 5. Target Configuration
echo "5. TARGET CONFIGURATION\n";
echo "───────────────────────────────────────────────────────────────\n";
$no_target = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0")->fetch_assoc();
echo "Investments without targets: " . $no_target['c'] . ($no_target['c'] > 0 ? " ⚠️" : " ✓") . "\n\n";

$target_periods = $conn->query("SELECT target_period, COUNT(*) as c FROM investments GROUP BY target_period");
echo "Target Period Distribution:\n";
while ($tp = $target_periods->fetch_assoc()) {
    $period = $tp['target_period'] ?: 'NULL';
    printf("  %-15s: %d assets\n", $period, $tp['c']);
}
echo "\n";

// 6. Asset Lifecycle
echo "6. ASSET LIFECYCLE STATUS\n";
echo "───────────────────────────────────────────────────────────────\n";
$statuses = $conn->query("SELECT status, COUNT(*) as c FROM investments GROUP BY status");
while ($st = $statuses->fetch_assoc()) {
    printf("%-15s: %d assets\n", $st['status'], $st['c']);
}
echo "\n";

$sold_check = $conn->query("
    SELECT COUNT(*) as with_data
    FROM investments 
    WHERE status = 'sold' 
    AND sale_price IS NOT NULL 
    AND sale_date IS NOT NULL
")->fetch_assoc();
$total_sold = $conn->query("SELECT COUNT(*) as c FROM investments WHERE status = 'sold'")->fetch_assoc();
echo "Sold assets with complete data: {$sold_check['with_data']} / {$total_sold['c']}\n\n";

// 7. Foreign Key Constraints
echo "7. FOREIGN KEY CONSTRAINTS\n";
echo "───────────────────────────────────────────────────────────────\n";
$fk_check = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('transactions', 'investments', 'vehicles')
");

if ($fk_check->num_rows > 0) {
    while ($fk = $fk_check->fetch_assoc()) {
        echo "✓ {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
} else {
    echo "⚠️  No foreign key constraints found\n";
}
echo "\n";

// 8. SUMMARY
echo "═══════════════════════════════════════════════════════════════\n";
echo "  SUMMARY & RECOMMENDATIONS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$issues = [];
$warnings = [];

if ($orphan_rev['count'] > 0) {
    $issues[] = "CRITICAL: {$orphan_rev['count']} revenue records not linked to investments";
}

if ($orphan_exp['count'] > 0) {
    $issues[] = "CRITICAL: {$orphan_exp['count']} expense records not linked to investments";
}

if ($no_target['c'] > 0) {
    $warnings[] = "WARNING: {$no_target['c']} investments lack performance targets";
}

if ($fk_check->num_rows == 0) {
    $warnings[] = "WARNING: No foreign key constraints enforcing referential integrity";
}

if (empty($issues) && empty($warnings)) {
    echo "✅ ARCHITECTURE SOUND - All checks passed\n\n";
} else {
    if (!empty($issues)) {
        echo "❌ CRITICAL ISSUES:\n";
        foreach ($issues as $issue) {
            echo "   • $issue\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠️  WARNINGS:\n";
        foreach ($warnings as $warning) {
            echo "   • $warning\n";
        }
        echo "\n";
    }
}

echo "═══════════════════════════════════════════════════════════════\n";
