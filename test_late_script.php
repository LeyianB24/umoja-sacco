<?php
require 'c:/xampp/htdocs/usms/config/app.php';
require 'c:/xampp/htdocs/usms/core/Services/CronService.php';
$c = new \USMS\Services\CronService();
$count = $c->sendBulkLateReminders();
echo "Count: " . $count . "\n";
$q = $conn->query("SELECT * FROM email_queue ORDER BY queue_id DESC LIMIT 5");
while($row = $q->fetch_assoc()) {
    echo "ID: {$row['queue_id']} | Status: {$row['status']} | Subject: {$row['subject']}\n";
}
