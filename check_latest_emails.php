<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT queue_id, recipient_email, recipient_name, status, last_error FROM email_queue ORDER BY queue_id DESC LIMIT 5");
echo "Latest emails in queue:\n";
while($r = $res->fetch_assoc()) {
    echo "ID: {$r['queue_id']} | Email: {$r['recipient_email']} | Name: {$r['recipient_name']} | Status: {$r['status']} | Error: " . ($r['last_error'] ?? 'None') . "\n";
}
