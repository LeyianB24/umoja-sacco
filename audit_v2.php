<?php
require_once 'config/db_connect.php';

$required_tables = [
    'members', 'transactions', 'permissions', 'role_permissions', 'cron_runs',
    'callback_logs', 'mpesa_requests', 'statutory_deductions', 'payroll_runs', 
    'payroll', 'salary_grades', 'employees', 'investments', 'loans', 
    'savings', 'shares', 'welfare', 'audit_logs', 'backups', 'mpesa_stk_requests'
];

echo "Database Tables Audit:\n";
foreach ($required_tables as $t) {
    echo "Processing $t...\n";
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    if ($res && $res->num_rows > 0) {
        echo " - Table '$t' EXISTS\n";
    } else {
        echo " - Table '$t' MISSING!\n";
    }
}

echo "\nChecking critical columns in 'transactions':\n";
$res = $conn->query("DESC transactions");
if ($res) {
    while($r = $res->fetch_assoc()) echo " - " . $r['Field'] . "\n";
}

echo "\nChecking critical columns in 'members':\n";
$res = $conn->query("DESC members");
if ($res) {
    while($r = $res->fetch_assoc()) echo " - " . $r['Field'] . "\n";
}
