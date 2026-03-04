<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE roles");
echo "--- roles table structure ---\n";
while($row = $res->fetch_assoc()) {
    foreach($row as $k => $v) echo "$k: $v | ";
    echo "\n";
}
?>
