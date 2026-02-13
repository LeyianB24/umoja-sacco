<?php
require 'config/db_connect.php';
echo "--- shares table ---\n";
$res = $conn->query("DESCRIBE shares");
while($row = $res->fetch_assoc()) echo "{$row['Field']} | {$row['Type']}\n";

$res = $conn->query("SELECT SUM(share_units) FROM shares");
$total = $res->fetch_row()[0];
echo "\nTotal Shares Issued: $total\n";
