<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("DESCRIBE members");
$cols = [];
while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
file_put_contents('c:/xampp/htdocs/usms/member_cols.txt', print_r($cols, true));
