<?php
require 'config/db_connect.php';
echo "Connected to Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n\n";
$res = $conn->query("SHOW TABLES");
echo "Tables:\n";
while($row = $res->fetch_row()) {
    echo "- " . $row[0] . "\n";
}
?>
