<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT total_raised FROM welfare_cases WHERE case_id = 4");
$row = $res->fetch_assoc();
echo "TOTAL_RAISED:" . $row['total_raised'] . "\n";
?>
