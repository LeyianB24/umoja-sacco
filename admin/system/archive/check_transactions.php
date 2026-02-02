<?php
require_once 'config/db_connect.php';
$res = $conn->query("DESC transactions");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
