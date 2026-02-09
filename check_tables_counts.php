<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SHOW TABLES");
$tables = [];
while($row = $res->fetch_row()) {
    $tables[] = $row[0];
}

$counts = [];
foreach ($tables as $t) {
    if (strpos($t, 'backup') !== false) continue;
    $cres = $conn->query("SELECT COUNT(*) FROM `$t` ");
    if ($cres) {
        $counts[$t] = $cres->fetch_row()[0];
    } else {
        $counts[$t] = "Error";
    }
}
echo json_encode($counts, JSON_PRETTY_PRINT);
