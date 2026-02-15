<?php
require_once 'config/db_connect.php';

function getTableSchema($conn, $table) {
    if ($conn->query("SHOW TABLES LIKE '$table'")->num_rows == 0) return "Table $table does not exist\n";
    
    $result = $conn->query("DESCRIBE $table");
    $output = "Schema for $table:\n";
    while ($row = $result->fetch_assoc()) {
        $output .= "{$row['Field']} ({$row['Type']}) " . ($row['Null']=='YES'?'NULL':'NOT NULL') . "\n";
    }
    return $output . "\n";
}

echo getTableSchema($conn, 'employees');
echo getTableSchema($conn, 'job_titles');
echo getTableSchema($conn, 'salary_grades');
echo getTableSchema($conn, 'roles');
echo getTableSchema($conn, 'permissions');
echo getTableSchema($conn, 'payroll_runs');
echo getTableSchema($conn, 'payroll_items');
echo getTableSchema($conn, 'statutory_rules');
echo getTableSchema($conn, 'payslips');
echo getTableSchema($conn, 'ledger_transactions');

?>
