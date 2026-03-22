<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$q = $conn->query('SELECT * FROM email_queue ORDER BY queue_id DESC LIMIT 10');
if(!$q) { echo "SQL Error: " . $conn->error; exit; }
while($row = $q->fetch_assoc()) {
    echo "ID: {$row['queue_id']} | To: {$row['recipient_email']} | Status: {$row['status']} | Subject: {$row['subject']} | Error: {$row['last_error']}\n";
}
