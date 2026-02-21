<?php
// api/v1/export_revenue.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';

use USMS\Services\UniversalExportEngine;

require_admin();
require_permission('revenue.php');

$format = $_GET['format'] ?? 'pdf';

// Fetch Data (Simplified for export)
$sql = "
    SELECT date, source, type, amount, description FROM (
        (SELECT i_inc.income_date as date, i.title as source, 'Income' as type, i_inc.amount, i_inc.description
         FROM investment_income i_inc JOIN investments i ON i_inc.investment_id = i.investment_id)
        UNION ALL
        (SELECT v_inc.income_date as date, i.reg_no as source, 'Income' as type, v_inc.amount, v_inc.description
         FROM vehicle_income v_inc JOIN investments i ON v_inc.vehicle_id = i.investment_id)
        UNION ALL
        (SELECT i_exp.expense_date as date, i.title as source, 'Expense' as type, i_exp.amount, i_exp.description
         FROM investment_expenses i_exp JOIN investments i ON i_exp.investment_id = i.investment_id)
        UNION ALL
        (SELECT v_exp.expense_date as date, i.reg_no as source, 'Expense' as type, v_exp.amount, v_exp.description
         FROM vehicle_expenses v_exp JOIN investments i ON v_exp.vehicle_id = i.investment_id)
    ) as combined
    ORDER BY date DESC
";

$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        $row['date'],
        $row['source'],
        $row['type'],
        number_format($row['amount'], 2),
        $row['description']
    ];
}

$headers = ['Date', 'Source', 'Type', 'Amount (KES)', 'Description'];
$title = "SACCO REVENUE REPORT - " . date('Y');

UniversalExportEngine::handle($format, $data, [
    'title' => $title,
    'module' => 'Revenue Module',
    'headers' => $headers
]);
exit;
