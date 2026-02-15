<?php
// Verify the enhanced report logic
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = ''; 
$dbname = 'umoja_drivers_sacco';
$conn = mysqli_connect($host, $user, $pass, $dbname);

$start_date = '2020-01-01'; 
$end_date = date('Y-m-d');

$inflow_dist = [
    'Deposits' => 0,
    'Repayments' => 0,
    'Shares' => 0,
    'Welfare' => 0,
    'Revenue' => 0,
    'Wallet' => 0,
    'Investments' => 0,
    'Other' => 0
];

echo "--- TESTING ENHANCED REPORT LOGIC ---\n";

$sql_dist = "SELECT la.category, SUM(le.credit) as val 
             FROM ledger_entries le
             JOIN ledger_accounts la ON le.account_id = la.account_id
             WHERE DATE(le.created_at) BETWEEN '$start_date' AND '$end_date' 
             AND (la.account_type IN ('liability', 'equity', 'revenue') OR la.category IN ('loans', 'investments'))
             GROUP BY la.category";

$res_dist = mysqli_query($conn, $sql_dist);

while($row = mysqli_fetch_assoc($res_dist)){
    $cat = strtolower($row['category']);
    echo "Processing Category: " . $row['category'] . " | Value: " . $row['val'] . "\n";
    
    if($cat == 'savings') $inflow_dist['Deposits'] = $row['val'];
    elseif($cat == 'loans') $inflow_dist['Repayments'] = $row['val'];
    elseif($cat == 'shares') $inflow_dist['Shares'] = $row['val'];
    elseif($cat == 'welfare') $inflow_dist['Welfare'] = $row['val'];
    elseif($cat == 'income' || $cat == 'revenue') $inflow_dist['Revenue'] = $row['val'];
    elseif($cat == 'wallet') $inflow_dist['Wallet'] = $row['val'];
    elseif($cat == 'investments') $inflow_dist['Investments'] = $row['val'];
    else $inflow_dist['Other'] += (float)$row['val'];
}

echo "\n--- FINAL BREAKDOWN ---\n";
foreach ($inflow_dist as $key => $val) {
    echo str_pad($key, 15) . number_format($val, 2) . "\n";
}
echo "TOTAL: " . number_format(array_sum($inflow_dist), 2) . "\n";
?>
