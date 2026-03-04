<?php
require 'config/db_connect.php';

echo "\n--- All Tables ---\n";
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}

echo "\n--- Members Columns ---\n";
$res = $conn->query("DESCRIBE members");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n--- Contributions Types ---\n";
$res = $conn->query("SELECT DISTINCT contribution_type FROM contributions");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
