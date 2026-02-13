<?php
require 'config/db_connect.php';

$data = [
    'cases' => [],
    'members' => [],
    'donations' => []
];

$res = $conn->query("SELECT case_id, title, related_member_id, status FROM welfare_cases");
while($row = $res->fetch_assoc()) $data['cases'][] = $row;

$res = $conn->query("SELECT member_id, full_name FROM members LIMIT 20");
while($row = $res->fetch_assoc()) $data['members'][] = $row;

$res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
while($row = $res->fetch_assoc()) $data['donations'][] = $row;

$res = $conn->query("SELECT * FROM welfare_support");
while($row = $res->fetch_assoc()) $data['support'][] = $row;

file_put_contents('assignment_data_v2.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Data written to assignment_data_v2.json";
?>
