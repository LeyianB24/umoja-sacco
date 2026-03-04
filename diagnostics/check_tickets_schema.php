<?php
require_once __DIR__ . '/../config/db_connect.php';
$res = $conn->query("DESCRIBE support_tickets");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
