<?php
require 'config/db_connect.php';

$tables = ['savings', 'shares'];
foreach($tables as $t) {
    echo "\n--- $t Columns ---\n";
    $res = $conn->query("DESCRIBE $t");
    while($row = $res->fetch_assoc()) echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
