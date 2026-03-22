<?php
require 'config/app.php';
$stmt = $conn->prepare("SELECT member_reg_no, email, full_name FROM members WHERE phone = '+254796157265' LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
$member = $res->fetch_assoc();
if ($member) {
    echo "Found member: " . json_encode($member, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Member not found.\n";
}
$stmt->close();
