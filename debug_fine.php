<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';

$loan_id = 26;
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

echo "--- BEFORE --- \n";
$res = $conn->query("SELECT status, next_repayment_date, current_balance FROM loans WHERE loan_id = $loan_id");
print_r($res->fetch_assoc());

$conn->query("UPDATE loans SET next_repayment_date = '$yesterday', status = 'disbursed' WHERE loan_id = $loan_id");
$conn->query("DELETE FROM fines WHERE loan_id = $loan_id AND date_applied = '$today'");

echo "RUNNING JOB...\n";
$out = shell_exec("php c:/xampp/htdocs/usms/cron/run.php daily_fines");
echo "OUTPUT: $out\n";

echo "--- AFTER --- \n";
$res = $conn->query("SELECT status, next_repayment_date, current_balance FROM loans WHERE loan_id = $loan_id");
print_r($res->fetch_assoc());

$res = $conn->query("SELECT * FROM fines WHERE loan_id = $loan_id AND date_applied = '$today'");
if ($row = $res->fetch_assoc()) {
    echo "FINE RECORD FOUND: \n";
    print_r($row);
} else {
    echo "NO FINE RECORD FOUND.\n";
}
