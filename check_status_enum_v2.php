<?php
require 'config/db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM welfare_cases LIKE 'status'");
$row = $res->fetch_assoc();
echo $row['Type'] . "\n";
?>
