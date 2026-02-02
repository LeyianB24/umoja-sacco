<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE notifications");
$out = "";
if($res) {
    while($row = $res->fetch_assoc()) {
        $out .= $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
file_put_contents('notifications_schema.txt', $out);
echo "Done";
