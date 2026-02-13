<?php
require 'config/db_connect.php';
$res = $conn->query("SHOW TABLES LIKE '%welfare%'");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>
