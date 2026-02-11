<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SELECT * FROM vehicles");
$vehicles = [];
while($row = $res->fetch_assoc()) {
    $vehicles[] = $row;
}
$json = json_encode($vehicles, JSON_PRETTY_PRINT);
$file = fopen('vehicles_data_utf8.json', 'w');
fwrite($file, $json);
fclose($file);
echo "Done\n";
