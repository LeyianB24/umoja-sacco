<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Loading config...\n";
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';

echo "2. Loading Auth...\n";
require_once __DIR__ . '/inc/Auth.php';

echo "3. Testing ksh()...\n";
echo ksh(100) . "\n";

echo "4. Loading LayoutManager & ReportGenerator...\n";
require_once __DIR__ . '/inc/LayoutManager.php';
require_once __DIR__ . '/inc/ReportGenerator.php';
require_once __DIR__ . '/vendor/autoload.php';

echo "Instantiating ReportGenerator...\n";
$rg = new ReportGenerator($conn);
echo "ReportGenerator instantiated.\n";

echo "5. Testing Report Logic...\n";
$start_date = '2024-01-01';
$end_date = date('Y-m-d');
$inflow_dist = [
    'Deposits' => 0, 'Repayments' => 0, 'Shares' => 0, 
    'Welfare' => 0, 'Revenue' => 0, 'Wallet' => 0, 
    'Investments' => 0, 'Other' => 0
];

$sql_dist = "SELECT la.category, SUM(le.credit) as val 
             FROM ledger_entries le
             JOIN ledger_accounts la ON le.account_id = la.account_id
             WHERE DATE(le.created_at) BETWEEN ? AND ? 
             AND (la.account_type IN ('liability', 'equity', 'revenue') OR la.category IN ('loans', 'investments'))
             GROUP BY la.category";
$stmt = $conn->prepare($sql_dist);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_dist = $stmt->get_result();
while($row = $res_dist->fetch_assoc()){
    echo "Row: " . $row['category'] . "\n";
}
echo "Done.\n";
?>
