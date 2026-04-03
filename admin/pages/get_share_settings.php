<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT * FROM share_settings LIMIT 1");
print_r($res->fetch_assoc());
echo "\n";
$res = $conn->query("SHOW COLUMNS FROM share_settings");
while($row = $res->fetch_assoc()) echo $row['Field'] . ' ' . $row['Type'] . "\n";
