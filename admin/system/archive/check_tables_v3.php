<?php
require 'config/app.php';
$res = $conn->query("SHOW TABLES");
$tables = [];
while($row = $res->fetch_row()) {
    $tables[] = $row[0];
}
echo json_encode($tables);
