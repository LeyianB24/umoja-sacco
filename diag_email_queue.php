<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
echo "Email Queue Statistics:\n";
while($row = $res->fetch_assoc()) {
    echo "- " . $row['status'] . ": " . $row['count'] . "\n";
}

echo "\nLatest Email Details (Last 10):\n";
$res2 = $conn->query("SELECT queue_id, recipient_email, status, attempts, last_error, created_at, sent_at FROM email_queue ORDER BY queue_id DESC LIMIT 10");
while($q = $res2->fetch_assoc()) {
    echo "ID: " . $q['queue_id'] . " | Ref: " . $q['recipient_email'] . " | Status: " . $q['status'] . " | Attempts: " . $q['attempts'] . " | Error: " . ($q['last_error'] ?? 'None') . " | Created: " . $q['created_at'] . " | Sent: " . ($q['sent_at'] ?? 'N/A') . "\n";
}
