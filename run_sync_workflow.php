<?php
require 'config/db_connect.php';

// 1. Assign orphaned cases to random members
// Valid members: 3, 5, 7, 8, 9, 10
$assignments = [
    1 => 3, // Financial Help For Leyian -> Tomaka Bezalel
    2 => 5  // Medical Support for Njoroge -> Daniel Soine Tomaka
];

foreach ($assignments as $cid => $mid) {
    $conn->query("UPDATE welfare_cases SET related_member_id = $mid WHERE case_id = $cid");
    echo "Assigned Case $cid to Member $mid\n";
}

// 2. Synchronize total_raised with welfare_donations
$res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
while ($row = $res->fetch_assoc()) {
    $cid = $row['case_id'];
    $total = $row['total'];
    $conn->query("UPDATE welfare_cases SET total_raised = $total WHERE case_id = $cid");
    echo "Synced Case $cid: Raised = $total\n";
}

// 3. Ensure target amounts are reasonable for these cases
$conn->query("UPDATE welfare_cases SET target_amount = 120000 WHERE case_id = 1");
$conn->query("UPDATE welfare_cases SET target_amount = 250000 WHERE case_id = 2");

echo "Workflow sync complete.\n";
?>
