<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$q = $conn->query("SELECT loan_id, amount, duration_months, total_payable, current_balance, next_repayment_date, disbursed_date, created_at FROM loans WHERE status IN ('active', 'disbursed') LIMIT 5");
while($row = $q->fetch_assoc()) echo json_encode($row) . "\n";
