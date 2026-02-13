<?php
// list_tables.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
?>
