<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$targetDate = date('Y-m-d', strtotime('+3 days'));
echo "Target Date: $targetDate\n";
$res = $conn->query("SELECT loan_id, member_id, next_repayment_date, status FROM loans LIMIT 10");
if($res) {
    while($r = $res->fetch_assoc()) { print_r($r); }
} else { echo "Error: " . $conn->error; }
