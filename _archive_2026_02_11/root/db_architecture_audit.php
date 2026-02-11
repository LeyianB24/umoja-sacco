<?php
/**
 * Database Architecture Audit
 * Validates investment-centric financial architecture
 */

require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════════════════\n";
echo "  USMS DATABASE ARCHITECTURE AUDIT\n";
echo "  Investment-Centric Financial Engine Validation\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. INVESTMENTS TABLE STRUCTURE
echo "1️⃣  INVESTMENTS TABLE STRUCTURE\n";
echo "─────────────────────────────────────────────────────────────\n";
$inv_schema = $conn->query("SHOW CREATE TABLE investments")->fetch_assoc();
echo $inv_schema['Create Table'] . "\n\n";

// 2. TRANSACTIONS TABLE (Revenue & Expenses)
echo "2️⃣  TRANSACTIONS TABLE STRUCTURE\n";
echo "─────────────────────────────────────────────────────────────\n";
$trans_schema = $conn->query("SHOW CREATE TABLE transactions")->fetch_assoc();
echo $trans_schema['Create Table'] . "\n\n";

// 3. VEHICLES TABLE (Check if it should be merged)
echo "3️⃣  VEHICLES TABLE STRUCTURE\n";
echo "─────────────────────────────────────────────────────────────\n";
$veh_result = $conn->query("SHOW TABLES LIKE 'vehicles'");
if ($veh_result->num_rows > 0) {
    $veh_schema = $conn->query("SHOW CREATE TABLE vehicles")->fetch_assoc();
    echo $veh_schema['Create Table'] . "\n\n";
} else {
    echo "❌ Vehicles table does not exist\n\n";
}

// 4. DATA INTEGRITY CHECKS
echo "4️⃣  DATA INTEGRITY ANALYSIS\n";
echo "─────────────────────────────────────────────────────────────\n";

// Check for orphaned revenue (income without investment link)
$orphan_revenue = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();
echo "Orphaned Revenue Records: " . $orphan_revenue['count'] . "\n";

// Check for orphaned expenses
$orphan_expenses = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();
echo "Orphaned Expense Records: " . $orphan_expenses['count'] . "\n";

// Check revenue distribution by related_table
echo "\nRevenue Distribution by Source:\n";
$rev_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow')
    GROUP BY related_table
");
while ($row = $rev_dist->fetch_assoc()) {
    echo "  - " . ($row['related_table'] ?: 'NULL') . ": " . $row['count'] . " records, KES " . number_format($row['total']) . "\n";
}

// Check expense distribution by related_table
echo "\nExpense Distribution by Source:\n";
$exp_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow')
    GROUP BY related_table
");
while ($row = $exp_dist->fetch_assoc()) {
    echo "  - " . ($row['related_table'] ?: 'NULL') . ": " . $row['count'] . " records, KES " . number_format($row['total']) . "\n";
}

// 5. INVESTMENT CATEGORIES
echo "\n5️⃣  INVESTMENT CATEGORIES (Data-Driven Check)\n";
echo "─────────────────────────────────────────────────────────────\n";
$categories = $conn->query("SELECT DISTINCT category FROM investments ORDER BY category");
echo "Current Categories:\n";
while ($cat = $categories->fetch_assoc()) {
    $count = $conn->query("SELECT COUNT(*) as c FROM investments WHERE category = '{$cat['category']}'")->fetch_assoc();
    echo "  - {$cat['category']}: {$count['c']} assets\n";
}

// 6. TARGET CONFIGURATION
echo "\n6️⃣  TARGET CONFIGURATION ANALYSIS\n";
echo "─────────────────────────────────────────────────────────────\n";
$no_target = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0")->fetch_assoc();
echo "Investments without targets: " . $no_target['c'] . "\n";

$target_periods = $conn->query("SELECT target_period, COUNT(*) as c FROM investments GROUP BY target_period");
echo "Target Period Distribution:\n";
while ($tp = $target_periods->fetch_assoc()) {
    echo "  - " . ($tp['target_period'] ?: 'NULL') . ": " . $tp['c'] . " assets\n";
}

// 7. ASSET LIFECYCLE
echo "\n7️⃣  ASSET LIFECYCLE MANAGEMENT\n";
echo "─────────────────────────────────────────────────────────────\n";
$statuses = $conn->query("SELECT status, COUNT(*) as c FROM investments GROUP BY status");
echo "Investment Status Distribution:\n";
while ($st = $statuses->fetch_assoc()) {
    echo "  - {$st['status']}: {$st['c']} assets\n";
}

// Check if sold investments have sale data
$sold_check = $conn->query("
    SELECT COUNT(*) as with_data
    FROM investments 
    WHERE status = 'sold' 
    AND sale_price IS NOT NULL 
    AND sale_date IS NOT NULL
")->fetch_assoc();
$total_sold = $conn->query("SELECT COUNT(*) as c FROM investments WHERE status = 'sold'")->fetch_assoc();
echo "\nSold Assets with Complete Data: {$sold_check['with_data']} / {$total_sold['c']}\n";

// 8. FOREIGN KEY CONSTRAINTS
echo "\n8️⃣  FOREIGN KEY CONSTRAINTS\n";
echo "─────────────────────────────────────────────────────────────\n";
$fk_check = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('transactions', 'investments', 'vehicles')
");

if ($fk_check->num_rows > 0) {
    while ($fk = $fk_check->fetch_assoc()) {
        echo "  ✓ {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
} else {
    echo "  ⚠️  No foreign key constraints found on core tables\n";
}

// 9. RECOMMENDATIONS
echo "\n9️⃣  ARCHITECTURAL RECOMMENDATIONS\n";
echo "─────────────────────────────────────────────────────────────\n";

$issues = [];

if ($orphan_revenue['count'] > 0) {
    $issues[] = "❌ {$orphan_revenue['count']} revenue records are not linked to investments";
}

if ($orphan_expenses['count'] > 0) {
    $issues[] = "❌ {$orphan_expenses['count']} expense records are not linked to investments";
}

if ($no_target['c'] > 0) {
    $issues[] = "⚠️  {$no_target['c']} investments lack performance targets";
}

if ($fk_check->num_rows == 0) {
    $issues[] = "⚠️  No foreign key constraints enforcing referential integrity";
}

if (empty($issues)) {
    echo "✅ Architecture appears sound - all checks passed\n";
} else {
    foreach ($issues as $issue) {
        echo $issue . "\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  AUDIT COMPLETE\n";
echo "═══════════════════════════════════════════════════════════════\n";
