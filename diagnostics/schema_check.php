<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

function get_columns($conn, $table) {
    echo "TABLE: $table\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Default'] . "\n";
    }
    echo "\n";
}

get_columns($conn, 'support_tickets');
get_columns($conn, 'admins');
get_columns($conn, 'roles'); // if exists
