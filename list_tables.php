<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query('SHOW TABLES');
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
