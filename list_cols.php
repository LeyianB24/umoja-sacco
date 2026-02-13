<?php
// list_cols.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM welfare_cases");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
