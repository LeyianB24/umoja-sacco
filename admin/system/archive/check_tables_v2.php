<?php
require 'config/app.php';
$res = $conn->query("SHOW TABLES");
echo "Tables:\n";
while($row = $res->fetch_row()) {
    echo "- " . $row[0] . "\n";
}
