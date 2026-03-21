<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("DESCRIBE members");
echo "Members Table Schema:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}
?>
