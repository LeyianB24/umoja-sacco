<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT case_id, title, status FROM welfare_cases");
if ($res->num_rows === 0) {
    echo "No cases found in database.\n";
} else {
    while($row = $res->fetch_assoc()) {
        echo "ID: " . $row['case_id'] . " | Title: " . $row['title'] . " | Status: " . $row['status'] . "\n";
    }
}
?>
