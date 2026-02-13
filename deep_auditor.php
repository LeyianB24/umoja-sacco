<?php
require 'config/db_connect.php';

$data = [
    'active_welfare_contribs' => [],
    'existing_donations' => []
];

$res = $conn->query("SELECT * FROM contributions WHERE contribution_type = 'welfare' AND status = 'active'");
while($row = $res->fetch_assoc()) $data['active_welfare_contribs'][] = $row;

$res = $conn->query("SELECT * FROM welfare_donations");
while($row = $res->fetch_assoc()) $data['existing_donations'][] = $row;

file_put_contents('deep_audit_data.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Deep audit written to deep_audit_data.json\n";
?>
