<?php
require 'config/db_connect.php';

$data = [
    'case_4' => null,
    'welfare_donations_case_4' => [],
    'recent_welfare_contributions' => [],
    'all_welfare_donations_totals' => []
];

// 1. Case #4 info
$res = $conn->query("SELECT * FROM welfare_cases WHERE case_id = 4");
$data['case_4'] = $res->fetch_assoc();

// 2. Donations for Case #4
$res = $conn->query("SELECT * FROM welfare_donations WHERE case_id = 4");
while($row = $res->fetch_assoc()) $data['welfare_donations_case_4'][] = $row;

// 3. Recent welfare contributions
$res = $conn->query("SELECT * FROM contributions WHERE contribution_type = 'welfare' ORDER BY created_at DESC LIMIT 20");
while($row = $res->fetch_assoc()) $data['recent_welfare_contributions'][] = $row;

// 4. Summarized donations per case
$res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
while($row = $res->fetch_assoc()) $data['all_welfare_donations_totals'][] = $row;

file_put_contents('donation_audit.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Audit written to donation_audit.json";
?>
