<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

function dumpTable($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
}

dumpTable($conn, 'employees');
dumpTable($conn, 'admins');
dumpTable($conn, 'roles');
