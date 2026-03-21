<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SHOW COLUMNS FROM members");
while($r = $res->fetch_assoc()) {
    echo "COL: " . $r['Field'] . "\n";
}
?>
