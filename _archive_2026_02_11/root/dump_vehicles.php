<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SELECT * FROM vehicles");
$vehicles = [];
while($row = $res->fetch_assoc()) {
    $vehicles[] = $row;
}
echo json_encode($vehicles, JSON_PRETTY_PRINT);
