<?php
include 'config/db_connect.php';
$res = $conn->query("DESCRIBE admins");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . PHP_EOL;
}
