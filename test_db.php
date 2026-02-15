<?php
require_once __DIR__ . '/config/db_connect.php';

echo "Testing database connection...\n";
echo "Database: " . ($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'NONE') . "\n\n";

// Test creating a simple table
echo "Testing table creation...\n";
$testSQL = "CREATE TABLE IF NOT EXISTS test_migration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100)
) ENGINE=InnoDB";

if ($conn->query($testSQL)) {
    echo "✓ Test table created successfully\n";
    $conn->query("DROP TABLE test_migration");
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

// Check if employees table already exists
$result = $conn->query("SHOW TABLES LIKE 'employees'");
if ($result && $result->num_rows > 0) {
    echo "\n✓ 'employees' table already exists\n";
    $conn->query("DESCRIBE employees");
} else {
    echo "\n✗ 'employees' table does not exist\n";
}

$conn->close();
?>
