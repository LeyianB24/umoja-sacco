<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

function dumpTable($conn, $table) {
    $res = $conn->query("DESCRIBE $table");
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return "--- $table ---\n" . json_encode($rows, JSON_PRETTY_PRINT) . "\n\n";
}

$out = dumpTable($conn, 'employees');
$out .= dumpTable($conn, 'admins');
$out .= dumpTable($conn, 'roles');

file_put_contents(__DIR__ . '/schema_dump_v2.txt', $out);
echo "Dumped to schema_dump_v2.txt\n";
