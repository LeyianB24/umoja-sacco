<?php
require 'C:\xampp\htdocs\usms\config\db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM notifications");
if ($res) {
    while($r = $res->fetch_assoc()) echo $r['Field'] . " (" . $r['Type'] . ")\n";
} else {
    echo "Error: " . $conn->error;
}
