<?php
require_once __DIR__ . '/config/db_connect.php';

$tables = ['ledger_transactions', 'transactions'];
foreach ($tables as $table) {
    echo "--- DESCRIBE $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            if (strpos($row['Field'], 'related') !== false) {
                echo $row['Field'] . " " . $row['Type'] . "\n";
            }
        }
    } else {
        echo "Table $table not found or error: " . $conn->error . "\n";
    }

    echo "\n";
}
