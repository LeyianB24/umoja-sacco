<?php
// check_packet_size.php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
$row = $res->fetch_assoc();
echo "max_allowed_packet: " . ($row['Value'] / 1024 / 1024) . " MB\n";
