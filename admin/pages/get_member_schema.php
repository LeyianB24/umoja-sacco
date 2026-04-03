<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SHOW COLUMNS FROM members");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' ' . $row['Type'] . "\n";
}
