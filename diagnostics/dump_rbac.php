<?php
define('DIAG_MODE', true);
require_once __DIR__ . '/../config/app.php';

function dumpTable($conn, $table) {
    echo "=== Data from $table ===\n";
    $res = $conn->query("SELECT * FROM $table");
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
    echo "\n";
}

dumpTable($conn, 'roles');
dumpTable($conn, 'permissions');
?>
