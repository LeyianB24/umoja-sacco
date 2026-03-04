<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE notifications");
if($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Table not found";
}
