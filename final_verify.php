<?php
require 'config/db_connect.php';

$res = $conn->query("SELECT case_id, title, total_raised FROM welfare_cases");
echo "--- WELFARE CASES ---\n";
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['case_id']} | Raised: {$row['total_raised']} | Title: {$row['title']}\n";
}

$res = $conn->query("SELECT case_id, SUM(amount) as actual_total FROM welfare_donations GROUP BY case_id");
echo "\n--- ACTUAL DONATION TOTALS ---\n";
while($row = $res->fetch_assoc()) {
    echo "Case ID: {$row['case_id']} | Actual Total: {$row['actual_total']}\n";
}

$res = $conn->query("SELECT * FROM welfare_donations WHERE case_id = 4 ORDER BY donation_date DESC");
echo "\n--- SAMPLE DONATIONS FOR CASE #4 ---\n";
$count = 0;
while($row = $res->fetch_assoc()) {
    if ($count < 5) echo "Date: {$row['donation_date']} | Amount: {$row['amount']} | Ref: {$row['reference_no']}\n";
    $count++;
}
echo "Total Donations for Case #4: $count\n";

?>
