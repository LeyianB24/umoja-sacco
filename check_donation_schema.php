<?php
require 'config/db_connect.php';
$res = $conn->query("SHOW CREATE TABLE contributions");
echo "CONTRIBUTIONS:\n" . $res->fetch_row()[1] . "\n\n";
$res = $conn->query("SHOW CREATE TABLE welfare_donations");
echo "WELFARE_DONATIONS:\n" . $res->fetch_row()[1] . "\n\n";
?>
