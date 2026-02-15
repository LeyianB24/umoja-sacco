<?php
// Deep Audit of ALL Inflows (Credits) to find missing money
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db_connect.php';

$log = fopen('audit_out.txt', 'w');

function logging($handle, $msg) {
    echo $msg;
    fwrite($handle, $msg);
}

logging($log, "--- DEEP AUDIT: ALL CREDITS (INFLOWS) ---\n");
logging($log, str_pad("CATEGORY", 15) . str_pad("TYPE", 12) . str_pad("ACCOUNT NAME", 30) . "TOTAL CREDIT\n");
logging($log, str_repeat("=", 70) . "\n");

$sql = "SELECT la.category, la.account_type, la.account_name, SUM(le.credit) as total
        FROM ledger_entries le 
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE le.credit > 0
        GROUP BY la.category, la.account_type, la.account_name
        ORDER BY la.category, la.account_type";

$result = $conn->query($sql);
$grand_total = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $grand_total += $row['total'];
        logging($log, str_pad($row['category'], 15) . 
             str_pad($row['account_type'], 12) . 
             str_pad(substr($row['account_name'], 0, 28), 30) . 
             number_format($row['total'], 2) . "\n");
    }
} else {
    logging($log, "Query Failed: " . $conn->error);
}

logging($log, str_repeat("=", 70) . "\n");
logging($log, str_pad("GRAND TOTAL", 57) . number_format($grand_total, 2) . "\n");

logging($log, "\n\n--- CURRENT REPORT QUERY RESULT ---\n");
// Run the exact query used in reports.php to see what matches
$start_date = '2020-01-01'; // Ancient date to catch all
$end_date = date('Y-m-d');

$sql_curr = "SELECT la.category, SUM(le.credit) as val 
             FROM ledger_entries le
             JOIN ledger_accounts la ON le.account_id = la.account_id
             WHERE DATE(le.created_at) BETWEEN '$start_date' AND '$end_date'
             AND (la.account_type IN ('liability', 'equity', 'revenue') OR la.category = 'loans' OR la.category = 'welfare')
             GROUP BY la.category";

$res_curr = $conn->query($sql_curr);
$report_total = 0;
while($row = $res_curr->fetch_assoc()) {
    $report_total += $row['val'];
    logging($log, "Report Category: " . $row['category'] . " = " . number_format($row['val'], 2) . "\n");
}
logging($log, "REPORT TOTAL: " . number_format($report_total, 2) . "\n");
logging($log, "MISSING: " . number_format($grand_total - $report_total, 2) . "\n");

fclose($log);
?>

?>
