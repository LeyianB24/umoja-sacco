<?php
require 'C:\xampp\htdocs\usms\config\db_connect.php';
$res = $conn->query("DESCRIBE welfare_support");
if ($res) {
    while($r = $res->fetch_assoc()) echo $r['Field'] . ' (' . $r['Type'] . ')' . "\n";
} else {
    echo "Table welfare_support doesn't exist or error.";
}
