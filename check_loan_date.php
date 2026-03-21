<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT next_repayment_date FROM loans WHERE loan_id = 26");
$row = $res->fetch_assoc();
file_put_contents('c:/xampp/htdocs/usms/loan_date.txt', print_r($row, true));
