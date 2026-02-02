<?php
require_once __DIR__ . '/../../config/db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM transactions");
while($f = $res->fetch_assoc()) echo $f['Field'] . "\n";
?>
