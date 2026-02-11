<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SELECT * FROM investments WHERE investment_id = 4");
echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
