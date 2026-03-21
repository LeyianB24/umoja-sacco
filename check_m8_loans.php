<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT loan_id, member_id, amount, status, next_repayment_date, current_balance FROM loans WHERE member_id = 8");
echo "Loans for Member 8 (bezaleltomaka@gmail.com):\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
    $is_overdue = (!empty($r['next_repayment_date']) && strtotime($r['next_repayment_date']) < time());
    echo "Is Overdue: " . ($is_overdue ? "YES" : "NO") . "\n";
}
