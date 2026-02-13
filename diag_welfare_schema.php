<?php
// diag_welfare_schema.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';

$tables = ['welfare_cases', 'welfare_support', 'welfare_donations', 'transactions', 'legacy_expenses_backup'];

foreach ($tables as $t) {
    echo "### Table: $t ###\n";
    $res = $conn->query("DESCRIBE `$t` ");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
        }
    } else {
        echo "Error on $t: " . $conn->error . "\n";
    }
    echo "\n";
}
?>
