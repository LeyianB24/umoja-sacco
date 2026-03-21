<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT loan_id, member_id, disbursed_date, next_repayment_date, current_balance, status FROM loans WHERE status IN ('active', 'disbursed')");
$loans = [];
while($row = $res->fetch_assoc()) $loans[] = $row;
file_put_contents('c:/xampp/htdocs/usms/current_loans.txt', print_r($loans, true));
