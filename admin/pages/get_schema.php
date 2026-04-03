<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SHOW COLUMNS FROM transactions");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' ' . $row['Type'] . "\n";
}
