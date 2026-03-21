<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT member_id, member_reg_no, savings_balance FROM members WHERE member_id = 8");
$row = $res->fetch_assoc();
echo "Member 8 Data:\n";
print_r($row);
?>
