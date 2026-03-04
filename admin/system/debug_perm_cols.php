<?php
require_once __DIR__ . '/../../config/db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM permissions");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
