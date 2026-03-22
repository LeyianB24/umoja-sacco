<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query('SELECT queue_id, recipient_email, status FROM email_queue ORDER BY queue_id DESC LIMIT 5');
if($res) {
    while($r = $res->fetch_assoc()) {
        print_r($r);
    }
} else { echo "Error or empty: " . $conn->error; }
