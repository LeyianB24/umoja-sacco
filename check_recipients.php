<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT queue_id, recipient_email, status, last_error FROM email_queue ORDER BY queue_id DESC LIMIT 4");
echo "Last 4 emails in queue:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}

$res2 = $conn->query("SELECT COUNT(*) as c FROM email_queue WHERE recipient_email = 'bezaleltomaka@gmail.com'");
$row = $res2->fetch_assoc();
echo "\nTotal emails for bezaleltomaka@gmail.com: " . $row['c'] . "\n";

$res3 = $conn->query("SELECT status, last_error FROM email_queue WHERE recipient_email = 'bezaleltomaka@gmail.com' ORDER BY queue_id DESC LIMIT 3");
while($r = $res3->fetch_assoc()) {
    print_r($r);
}
