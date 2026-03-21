<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';

echo "--- FINES APPLIED TODAY ---\n";
$today = date('Y-m-d');
$res = $conn->query("SELECT f.*, l.member_id FROM fines f JOIN loans l ON f.loan_id = l.loan_id WHERE f.date_applied = '$today'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- EMAIL QUEUE (RECENT) ---\n";
$res = $conn->query("SELECT queue_id, recipient_email, subject, status, created_at FROM email_queue ORDER BY created_at DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- LOAN 26 DATES ---\n";
$res = $conn->query("SELECT loan_id, next_repayment_date, last_repayment_date FROM loans WHERE loan_id = 26");
print_r($res->fetch_assoc());
