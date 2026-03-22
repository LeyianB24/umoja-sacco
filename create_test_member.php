<?php
require 'config/app.php';
$full_name = 'Test User';
$email = 'testmember@example.com';
$phone = '+254796157265';
$password = password_hash('Test@123', PASSWORD_DEFAULT);
$reg_no = 'M-TEST-001';

$stmt = $conn->prepare("INSERT INTO members (full_name, email, phone, password, member_reg_no, status, registration_fee_status) VALUES (?, ?, ?, ?, ?, 'active', 'unpaid')");
$stmt->bind_param("sssss", $full_name, $email, $phone, $password, $reg_no);

if ($stmt->execute()) {
    echo "Test member created successfully. Reg No: $reg_no, Password: Test@123\n";
} else {
    echo "Error creating test member: " . $conn->error . "\n";
}
$stmt->close();
