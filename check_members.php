<?php
require_once 'config/db_connect.php';
$res = $conn->query("DESCRIBE members");
echo "Columns in members:\n";
while($r = $res->fetch_assoc()) {
    echo " - " . $r['Field'] . "\n";
}
