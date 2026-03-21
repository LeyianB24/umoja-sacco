<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT loan_id, next_repayment_date, status FROM loans WHERE member_id = 8 AND status = 'disbursed'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
    $is_overdue = (!empty($row['next_repayment_date']) && strtotime($row['next_repayment_date']) < time());
    echo "Is Overdue: " . ($is_overdue ? "YES" : "NO") . "\n";
}
