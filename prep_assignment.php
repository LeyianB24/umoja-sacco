<?php
require 'config/db_connect.php';

echo "--- CASES ---\n";
$res = $conn->query("SELECT case_id, title, related_member_id, status FROM welfare_cases");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['case_id']} | Title: {$row['title']} | Member: " . ($row['related_member_id'] ?? 'NULL') . " | Status: {$row['status']}\n";
}

echo "\n--- MEMBERS ---\n";
$res = $conn->query("SELECT member_id, full_name FROM members LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['member_id']} | Name: {$row['full_name']}\n";
}

echo "\n--- DONATIONS/CONTRIBUTIONS FOR CASES ---\n";
$res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
while($row = $res->fetch_assoc()) {
    echo "Case ID: {$row['case_id']} | Raised: {$row['total']}\n";
}
?>
