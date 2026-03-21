<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT * FROM cron_runs ORDER BY id DESC LIMIT 1");
$row = $res->fetch_assoc();
file_put_contents('c:/xampp/htdocs/usms/cron_check.txt', print_r($row, true));
