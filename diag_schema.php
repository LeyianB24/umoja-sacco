<?php
// diag_schema.php
$config = 'c:\xampp\htdocs\usms\config\db_connect.php';
if (!file_exists($config)) die("Config not found\n");
require $config;

if (!isset($conn)) die("Conn not set\n");
if ($conn->connect_error) die("Connect failed: " . $conn->connect_error . "\n");

function diag($table) {
    global $conn;
    echo "### Table: $table ###\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} | {$row['Type']}\n";
        }
    } else {
        echo "Error on $table: " . $conn->error . "\n";
    }
    echo "\n";
}

diag('expenses');
diag('transactions');
diag('welfare_support');
diag('welfare_cases');
?>
