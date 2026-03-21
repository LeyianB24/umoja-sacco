<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT m.member_id, m.email, l.loan_id, l.next_repayment_date FROM loans l JOIN members m ON l.member_id = m.member_id WHERE l.loan_id IN (23, 24, 25, 27)");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
