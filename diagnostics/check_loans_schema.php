<?php
include 'c:/xampp/htdocs/usms/config/db_connect.php';
$res = $conn->query('DESCRIBE loans');
while($row = $res->fetch_assoc()) {
    echo "Field: " . $row['Field'] . "\n";
}
?>
