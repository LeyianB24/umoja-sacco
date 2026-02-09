<?php
require_once __DIR__ . '/config/db_connect.php';

echo "--- STARTING DATABASE RATIONALIZATION ---\n";

// 1. Check for legacy tables
$tables_to_check = ['investments_expenses', 'investments_income', 'vehicles_income', 'vehicles_expenses', 'expenses'];
$existing_tables = [];

$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    if (in_array($row[0], $tables_to_check)) {
        $existing_tables[] = $row[0];
    }
}

echo "Found legacy tables: " . implode(", ", $existing_tables) . "\n";

// 2. Describe relevant tables for audit report
$tables_to_audit = array_merge(['investments', 'vehicles', 'transactions'], $existing_tables);
foreach ($tables_to_audit as $t) {
    echo "\nStructure of table: $t\n";
    $res = $conn->query("DESCRIBE $t");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "  (Table does not exist)\n";
    }
}

// 3. Count records for migration planning
echo "\nRecord Counts:\n";
foreach ($tables_to_audit as $t) {
    $res = $conn->query("SELECT COUNT(*) FROM $t");
    if ($res) {
        $count = $res->fetch_row()[0];
        echo "  - $t: $count\n";
    }
}
