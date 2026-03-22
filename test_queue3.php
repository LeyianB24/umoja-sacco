<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$q = $conn->query('SELECT queue_id, status, last_error, subject, attempts FROM email_queue ORDER BY queue_id DESC LIMIT 15');
while($row = $q->fetch_assoc()) {
    echo "ID:{$row['queue_id']} | Stat:{$row['status']} | Att:{$row['attempts']} | Subj:{$row['subject']}\nErrors: {$row['last_error']}\n---\n";
}
