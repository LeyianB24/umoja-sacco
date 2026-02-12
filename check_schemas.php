<?php
include 'config/db_connect.php';
$res = $conn->query("DESCRIBE members");
echo "--- Members Table ---" . PHP_EOL;
while($row = $res->fetch_assoc()) echo $row['Field'] . PHP_EOL;

$res = $conn->query("DESCRIBE employees");
echo "--- Employees Table ---" . PHP_EOL;
while($row = $res->fetch_assoc()) echo $row['Field'] . PHP_EOL;
