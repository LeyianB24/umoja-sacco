<?php
require_once __DIR__ . '/../config/db_connect.php';
$r = $conn->query('DESCRIBE members');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}
?>
