<?php
require_once __DIR__ . '/config/db_connect.php';
$res = $conn->query("SELECT investment_id, title, reg_no FROM investments WHERE reg_no IS NOT NULL AND reg_no != ''");
$results = [];
while($row = $res->fetch_assoc()) {
    $results[] = $row;
}
echo json_encode($results, JSON_PRETTY_PRINT);
