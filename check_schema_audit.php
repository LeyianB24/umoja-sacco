<?php
require_once __DIR__ . '/config/db_connect.php';
$tables = ['admins', 'employees', 'investments', 'transactions', 'vehicle_income', 'vehicle_expenses'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Key']}\n";
        }
    } else {
        echo "Table does not exist.\n";
    }
    echo "\n";
}
?>
