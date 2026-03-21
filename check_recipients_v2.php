<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT queue_id, recipient_email, status, last_error FROM email_queue ORDER BY queue_id DESC LIMIT 4");
echo "Last 4 emails in queue:\n";
while($r = $res->fetch_assoc()) {
    echo "ID: {$r['queue_id']} | Email: {$r['recipient_email']} | Status: {$r['status']} | Error: " . ($r['last_error'] ?? 'None') . "\n";
}

$res2 = $conn->query("SELECT COUNT(*) as c FROM email_queue WHERE recipient_email = 'bezaleltomaka@gmail.com'");
$row = $res2->fetch_assoc();
echo "\nTotal emails for bezaleltomaka@gmail.com: " . $row['c'] . "\n";
