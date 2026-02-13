<?php
require 'config/db_connect.php';

echo "--- CASE #4 INFO ---\n";
$res = $conn->query("SELECT case_id, title, total_raised, target_amount FROM welfare_cases WHERE case_id = 4");
print_r($res->fetch_assoc());

echo "\n--- DONATIONS FOR CASE #4 ---\n";
$res = $conn->query("SELECT * FROM welfare_donations WHERE case_id = 4");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- CONTRIBUTIONS FOR WELFARE ---\n";
$res = $conn->query("SELECT * FROM contributions WHERE contribution_type = 'welfare' ORDER BY created_at DESC LIMIT 10");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- TOTAL DONATIONS Grouped by Case ---\n";
$res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
