<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT queue_id, recipient_email, status, last_error, created_at FROM email_queue ORDER BY queue_id DESC");
echo "Full email queue (Descending):\n";
while($r = $res->fetch_assoc()) {
    echo "ID: {$r['queue_id']} | Email: {$r['recipient_email']} | Status: {$r['status']} | Error: " . ($r['last_error'] ?? 'None') . " | Created: {$r['created_at']}\n";
}
