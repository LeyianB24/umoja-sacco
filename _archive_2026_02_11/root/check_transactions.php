<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("DESC transactions");
while ($row = $res->fetch_assoc()) {
    printf("%-20s %s\n", $row['Field'], $row['Type']);
}
?>
