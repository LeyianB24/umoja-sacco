<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE welfare_cases");
echo "---Columns---\n";
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "---End---\n";
