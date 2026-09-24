<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT * FROM roles");
echo "--- roles table ---\n";
while($row = $res->fetch_assoc()) {
    foreach($row as $k => $v) echo "$k: $v | ";
    echo "\n";
}

$res = $conn->query("SHOW COLUMNS FROM admins LIKE 'role'");
$row = $res->fetch_assoc();
echo "--- admins.role enum ---\n";
echo $row['Type'] . "\n";
?>
