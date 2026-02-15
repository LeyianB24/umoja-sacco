<?php
require_once __DIR__ . '/config/db_connect.php';

$output = "Checking Employee Architecture Schema...\n\n";

$requiredTables = [
    'employees', 'job_titles', 'salary_grades', 'job_role_mapping',
    'payroll_runs', 'payroll_items', 'statutory_rules', 'payslips'
];

$output .= "Table Status:\n=================================\n";

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = ($result && $result->num_rows > 0);
    
    if ($exists) {
        $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
        $output .= "✓ $table - EXISTS (Rows: $count)\n";
    } else {
        $output .= "✗ $table - MISSING\n";
    }
}

$output .= "=================================\n";

file_put_contents(__DIR__ . '/schema_status.txt', $output);
echo $output;

$conn->close();
?>
