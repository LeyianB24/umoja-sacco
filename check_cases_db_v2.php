<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT case_id, title, status FROM welfare_cases");
while($row = $res->fetch_assoc()) {
    printf("ID: %d | Status: %s | Title: %s\n", $row['case_id'], $row['status'], $row['title']);
}
?>
