<?php
require_once __DIR__ . '/config/db_connect.php';
echo "--- ROLES ---\n";
$res = $conn->query("SELECT * FROM roles");
while($row = $res->fetch_assoc()) print_r($row);
echo "--- PERMISSIONS ---\n";
$res = $conn->query("SELECT * FROM permissions LIMIT 10");
while($row = $res->fetch_assoc()) print_r($row);
?>
