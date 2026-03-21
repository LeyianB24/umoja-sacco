<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT COUNT(*) as total FROM email_queue WHERE status = 'pending'");
$row = $res->fetch_assoc();
echo "Total Pending Emails: " . $row['total'] . "\n";
$res2 = $conn->query("SELECT * FROM email_queue ORDER BY queue_id DESC LIMIT 5");
while($q = $res2->fetch_assoc()) {
    echo "ID: " . $q['queue_id'] . " | Recipient: " . $q['recipient_name'] . " | Subject: " . $q['subject'] . "\n";
}
