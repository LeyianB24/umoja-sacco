<?php
// schema_dump.php
$config = 'c:\xampp\htdocs\usms\config\db_connect.php';
require $config;

function dump_cols($table) {
    global $conn;
    echo "\n--- Columns for $table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . "\n";
        }
    } else {
        echo "Table not found: $table\n";
    }
}

dump_cols('expenses');
dump_cols('transactions');
dump_cols('welfare_cases');
dump_cols('welfare_support');
?>
