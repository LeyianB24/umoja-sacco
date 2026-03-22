<?php
require 'c:/xampp/htdocs/usms/config/app.php';

$q = $conn->query("SELECT * FROM loans WHERE status IN ('active', 'disbursed')");
if (!$q) { echo "SQL Error: " . $conn->error; exit; }

$updated = 0;
while($loan = $q->fetch_assoc()) {
    $id = $loan['loan_id'];
    $amt = (float)$loan['amount'];
    $rate = (float)$loan['interest_rate'];
    $tp = (float)$loan['total_payable'];
    $dur = (int)$loan['duration_months'];
    if($dur <= 0) $dur = 12;
    
    $base_tp = $tp > 0 ? $tp : ($amt * (1 + ($rate/100)));
    $monthly = $base_tp / $dur;
    
    $start_date = !empty($loan['disbursed_date']) ? $loan['disbursed_date'] : $loan['created_at'];
    
    $rep_q = $conn->query("SELECT SUM(amount_paid) as p FROM loan_repayments WHERE loan_id = {$id}");
    $paid = (float)($rep_q->fetch_assoc()['p'] ?? 0);
    
    $months_covered = floor($paid / ($monthly * 0.98));
    $next_add = $months_covered + 1;
    
    // Calculate new date
    $new_date_q = $conn->query("SELECT DATE_ADD('{$start_date}', INTERVAL {$next_add} MONTH) as nd");
    $new_date_str = $new_date_q->fetch_assoc()['nd'];
    $new_date = date('Y-m-d H:i:s', strtotime($new_date_str));
    
    $conn->query("UPDATE loans SET next_repayment_date = '{$new_date}' WHERE loan_id = {$id}");
    $updated++;
}
echo "Successfully adjusted {$updated} active loans.\n";
