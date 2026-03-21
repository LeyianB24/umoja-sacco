<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT queue_id, recipient_email, status, last_error FROM email_queue ORDER BY queue_id DESC LIMIT 5");
echo "Latest emails in queue:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}
