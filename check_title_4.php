<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SELECT title FROM investments WHERE investment_id = 4");
$row = $res->fetch_assoc();
echo $row['title'] . "\n";
