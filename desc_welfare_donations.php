<?php
require 'config/db_connect.php';
$res = $conn->query("DESCRIBE welfare_donations");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
