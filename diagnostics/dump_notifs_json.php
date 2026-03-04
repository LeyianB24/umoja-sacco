<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$res = $conn->query("DESCRIBE notifications");
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);
