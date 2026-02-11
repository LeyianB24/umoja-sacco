<?php
// tests/verify_member_status_v2.php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/RegistrationHelper.php';
require_once __DIR__ . '/../inc/functions.php';

echo "Starting verification of member activation upon registration payment...\n";

// 1. Create a dummy inactive member
$email = "test_" . time() . "@example.com";
$name = "Test Member";
$id = "ID" . time();
$phone = "+254" . rand(700000000, 799999999);
$reg_no = "T-" . rand(1000, 9999);

$stmt = $conn->prepare("INSERT INTO members (member_reg_no, full_name, national_id, phone, email, status, registration_fee_status, join_date) VALUES (?, ?, ?, ?, ?, 'inactive', 'unpaid', NOW())");
$stmt->bind_param("sssss", $reg_no, $name, $id, $phone, $email);
if (!$stmt->execute()) {
    die("Failed to create test member: " . $conn->error);
}
$member_id = $conn->insert_id;
echo "Test member created with ID: $member_id, Status: inactive\n";

// 2. Call markAsPaid
echo "Calling RegistrationHelper::markAsPaid...\n";
$ref = "VREF-" . time();
if (RegistrationHelper::markAsPaid($member_id, 1000.00, $ref, $conn)) {
    echo "markAsPaid returned true.\n";
} else {
    echo "markAsPaid returned false.\n";
}

// 3. Verify status in DB
$res = $conn->query("SELECT status, registration_fee_status FROM members WHERE member_id = $member_id");
$row = $res->fetch_assoc();

echo "Final Member Status: " . $row['status'] . "\n";
echo "Final Reg Fee Status: " . $row['registration_fee_status'] . "\n";

if ($row['status'] === 'active' && $row['registration_fee_status'] === 'paid') {
    echo "SUCCESS: Member was successfully activated!\n";
} else {
    echo "FAILURE: Member status or reg_fee_status is incorrect.\n";
}

// echo "Cleanup complete.\n";
