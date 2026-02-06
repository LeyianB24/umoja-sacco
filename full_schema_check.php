<?php
require_once __DIR__ . '/config/db_connect.php';
$tables = ['admins', 'employees', 'investments', 'transactions', 'vehicles'];
foreach ($tables as $table) {
    echo "=== Table: $table ===\n";
    $res = $conn->query("DESC $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            printf("%-20s %-20s %-10s %-10s %-20s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default']);
        }
    } else {
        echo "Table $table does not exist.\n";
    }
    echo "\n";
}
?>
