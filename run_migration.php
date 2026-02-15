<?php
require_once __DIR__ . '/config/db_connect.php';

echo "Running Employee Architecture Migration...\n\n";

$sqlFile = __DIR__ . '/database/migrations/employee_architecture.sql';
if (!file_exists($sqlFile)) {
    die("Error: Migration file not found\n");
}

$sql = file_get_contents($sqlFile);

// Use multi_query for better handling
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
        
        // Check for errors
        if ($conn->error) {
            echo "✗ Error: " . $conn->error . "\n";
        }
        
    } while ($conn->more_results() && $conn->next_result());
    
    echo "\n=================================\n";
    echo "Migration Complete!\n";
    echo "=================================\n";
    
} else {
    echo "✗ Migration failed: " . $conn->error . "\n";
}

// Verify tables were created
echo "\nVerifying tables...\n";
$tables = ['employees', 'job_titles', 'salary_grades', 'payroll_runs', 'payroll_items', 'statutory_rules', 'payslips'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' NOT found\n";
    }
}

$conn->close();
?>
