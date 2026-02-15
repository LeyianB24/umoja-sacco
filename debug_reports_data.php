<?php
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';

// 1. Check Date Range of Ledger
$row = $conn->query("SELECT MIN(created_at) as min_date, MAX(created_at) as max_date, COUNT(*) as count FROM ledger_entries")->fetch_assoc();
echo "--- Ledger Data Range ---\n";
echo "Min Date: " . $row['min_date'] . "\n";
echo "Max Date: " . $row['max_date'] . "\n";
echo "Total Entries: " . $row['count'] . "\n\n";

// 2. Test User's Liquidity Query (Current Month - Feb 2026)
$liquidity_names = "'Cash at Hand', 'M-Pesa Float', 'Bank Account', 'Paystack Clearing Account'";
$start = '2026-02-01';
$end = '2026-02-28';

$sql = "SELECT 
    SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.debit ELSE 0 END) as total_inflow,
    SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.credit ELSE 0 END) as total_outflow
    FROM ledger_entries le
    JOIN ledger_accounts la ON le.account_id = la.account_id
    WHERE DATE(le.created_at) BETWEEN '$start' AND '$end'";

$res = $conn->query($sql)->fetch_assoc();
echo "--- Feb 2026 Liquidity Flow ---\n";
echo "Inflow: " . $res['total_inflow'] . "\n";
echo "Outflow: " . $res['total_outflow'] . "\n\n";

// 3. Test Inflow Distribution (Feb 2026)
$sql_dist = "SELECT la.category, SUM(le.credit) as val 
             FROM ledger_entries le
             JOIN ledger_accounts la ON le.account_id = la.account_id
             WHERE DATE(le.created_at) BETWEEN '$start' AND '$end' 
             AND (la.account_type IN ('liability', 'equity', 'revenue') OR la.category IN ('loans', 'investments'))
             GROUP BY la.category";
$res_dist = $conn->query($sql_dist);
echo "--- Feb 2026 Distribution ---\n";
while($r = $res_dist->fetch_assoc()) {
    echo $r['category'] . ": " . $r['val'] . "\n";
}
?>
