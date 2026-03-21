<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT member_id, member_reg_no, full_name, email, phone FROM members WHERE member_id = 8");
$row = $res->fetch_assoc();
echo "Member 8 Key Data:\n";
print_r($row);
?>
