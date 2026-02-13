<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT * FROM welfare_cases LIMIT 20");
echo "Count: " . $res->num_rows . "\n";
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['case_id'] . " | Status: [" . $row['status'] . "] | Title: " . $row['title'] . "\n";
}
?>
