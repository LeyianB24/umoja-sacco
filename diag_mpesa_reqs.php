<?php
require 'config/app.php';
$res = $conn->query("SELECT * FROM mpesa_requests ORDER BY created_at DESC LIMIT 5");
$rows = [];
while($row = $res->fetch_assoc()) $rows[] = $row;
echo json_encode($rows, JSON_PRETTY_PRINT);
