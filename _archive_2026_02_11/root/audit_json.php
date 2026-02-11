<?php
require_once __DIR__ . '/config/db_connect.php';

// Get orphaned revenue
$orphan_rev = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

// Get orphaned expenses
$orphan_exp = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

// Get revenue distribution
$rev_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow')
    GROUP BY related_table
")->fetch_all(MYSQLI_ASSOC);

// Get expense distribution
$exp_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow')
    GROUP BY related_table
")->fetch_all(MYSQLI_ASSOC);

// Get investments without targets
$no_target = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0")->fetch_assoc();

// Get total counts
$total_investments = $conn->query("SELECT COUNT(*) as c FROM investments")->fetch_assoc();
$total_transactions = $conn->query("SELECT COUNT(*) as c FROM transactions")->fetch_assoc();

$result = [
    'orphaned_revenue' => $orphan_rev['count'],
    'orphaned_expenses' => $orphan_exp['count'],
    'revenue_distribution' => $rev_dist,
    'expense_distribution' => $exp_dist,
    'investments_without_targets' => $no_target['c'],
    'total_investments' => $total_investments['c'],
    'total_transactions' => $total_transactions['c']
];

echo json_encode($result, JSON_PRETTY_PRINT);
