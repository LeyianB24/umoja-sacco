<?php
require_once 'config/db_connect.php';

$required_tables = [
    'members', 'transactions', 'permissions', 'role_permissions', 'cron_runs',
    'callback_logs', 'mpesa_requests', 'statutory_deductions', 'payroll_runs', 
    'payroll', 'salary_grades', 'employees', 'investments', 'loans', 
    'savings', 'shares', 'welfare', 'audit_logs', 'backups', 'mpesa_stk_requests'
];

echo "Database: " . DB_NAME . "\n";
foreach ($required_tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    if ($res && $res->num_rows > 0) {
        echo " - Table '$t' EXISTS\n";
    } else {
        echo " - Table '$t' MISSING!\n";
    }
}
