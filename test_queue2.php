<?php
require 'c:/xampp/htdocs/usms/config/app.php';
$q = $conn->query('SELECT queue_id, status, last_error FROM email_queue ORDER BY queue_id DESC LIMIT 10');
while($row = $q->fetch_assoc()) echo json_encode($row) . "\n";
