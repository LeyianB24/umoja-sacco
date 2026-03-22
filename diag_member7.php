<?php
require 'config/app.php';
$res = $conn->query("SELECT member_id, full_name, phone FROM members WHERE member_id = 7");
echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
