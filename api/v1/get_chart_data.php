<?php
/**
 * api/v1/get_chart_data.php
 * Secure JSON data for Revenue vs Expenses Chart
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';

// 1. Auth Guard
try {
    require_admin();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// 2. Aggregate Data (Last 6 Months)
$sql = "
    SELECT DATE_FORMAT(date, '%b %Y') as month, SUM(income) as inc, SUM(expense) as exp 
    FROM (
        SELECT income_date as date, amount as income, 0 as expense FROM investment_income
        UNION ALL SELECT income_date, amount, 0 FROM vehicle_income
        UNION ALL SELECT expense_date, 0, amount FROM investment_expenses
        UNION ALL SELECT expense_date, 0, amount FROM vehicle_expenses
    ) as combined 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY MIN(date) ASC
";

$res = $conn->query($sql);
$labels = [];
$income = [];
$expenses = [];

while ($row = $res->fetch_assoc()) {
    $labels[]   = $row['month'];
    $income[]   = (float)$row['inc'];
    $expenses[] = (float)$row['exp'];
}

echo json_encode([
    'labels'   => $labels,
    'income'   => $income,
    'expenses' => $expenses
]);
exit;
