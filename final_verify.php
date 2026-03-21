<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$fines = $conn->query("SELECT COUNT(*) FROM fines WHERE date_applied = CURDATE()")->fetch_row()[0];
$emails = [];
$res = $conn->query("SELECT subject FROM email_queue ORDER BY created_at DESC LIMIT 2");
while($r = $res->fetch_row()) $emails[] = $r[0];
$loan = $conn->query("SELECT next_repayment_date FROM loans WHERE loan_id = 26")->fetch_assoc();

echo "Fines applied today: $fines\n";
echo "Recent emails:\n";
foreach($emails as $e) echo " - $e\n";
echo "Loan 26 Next Due: " . $loan['next_repayment_date'] . "\n";
