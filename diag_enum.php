<?php
require 'config/db_connect.php';
$res = $conn->query("SELECT case_id, status, CAST(status AS UNSIGNED) as status_int FROM welfare_cases LIMIT 20");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['case_id'] . " | Status: '" . $row['status'] . "' | Int: " . $row['status_int'] . "\n";
}
?>
