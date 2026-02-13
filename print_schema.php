<?php
require 'config/db_connect.php';
$res = $conn->query("SHOW CREATE TABLE contributions");
$c = $res->fetch_row()[1];
echo "--- CONTRIBUTIONS ---\n$c\n\n";

$res = $conn->query("SHOW CREATE TABLE welfare_donations");
$w = $res->fetch_row()[1];
echo "--- WELFARE_DONATIONS ---\n$w\n\n";
?>
