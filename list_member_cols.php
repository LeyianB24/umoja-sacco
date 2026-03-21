<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("DESCRIBE members");
while($r = $res->fetch_assoc()) {
    echo $r['Field'] . "\n";
}
?>
