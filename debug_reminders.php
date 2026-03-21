<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';

$res = $conn->query("SELECT loan_id, member_id, next_repayment_date FROM loans WHERE loan_id = 2");
$loan = $res->fetch_assoc();
print_r($loan);

$res = $conn->query("SELECT member_id, first_name, email FROM members WHERE member_id = " . $loan['member_id']);
$member = $res->fetch_assoc();
print_r($member);

$targetDate = date('Y-m-d', strtotime('+3 days'));
echo "Target Date: $targetDate\n";

$sql = "SELECT l.loan_id FROM loans l
        JOIN members m ON l.member_id = m.member_id
        WHERE l.loan_id = 2
        AND l.status IN ('active', 'disbursed') 
        AND DATE(l.next_repayment_date) = '$targetDate'
        AND m.email IS NOT NULL";
$res = $conn->query($sql);
echo "Match Found: " . ($res->num_rows > 0 ? "YES" : "NO") . "\n";
