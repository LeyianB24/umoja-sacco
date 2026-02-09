<?php
require_once __DIR__ . '/config/db_connect.php';

// Query 1: Orphaned Revenue
$result1 = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
");
$orphan_rev = $result1->fetch_assoc()['count'];

// Query 2: Orphaned Expenses
$result2 = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
");
$orphan_exp = $result2->fetch_assoc()['count'];

// Query 3: Investments without targets
$result3 = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0");
$no_target = $result3->fetch_assoc()['c'];

// Query 4: Total investments
$result4 = $conn->query("SELECT COUNT(*) as c FROM investments");
$total_inv = $result4->fetch_assoc()['c'];

echo "Orphaned Revenue: $orphan_rev\n";
echo "Orphaned Expenses: $orphan_exp\n";
echo "Investments without targets: $no_target\n";
echo "Total Investments: $total_inv\n";

if ($orphan_rev > 0 || $orphan_exp > 0) {
    echo "\nCRITICAL ISSUES FOUND!\n";
} else {
    echo "\nNo critical issues.\n";
}
