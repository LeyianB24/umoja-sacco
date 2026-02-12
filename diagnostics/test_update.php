<?php
include 'c:/xampp/htdocs/usms/config/db_connect.php';
$member_id = 2; // From diagnostic
$email = 'alice' . time() . '@example.com';
$phone = '+254700000000';
$gender = 'female';
$address = 'Test Address ' . time();

$stmt = $conn->prepare("UPDATE members SET email=?, phone=?, gender=?, address=? WHERE member_id=?");
$stmt->bind_param("ssssi", $email, $phone, $gender, $address, $member_id);

if ($stmt->execute()) {
    echo "Update successful for ID $member_id\n";
} else {
    echo "Update failed: " . $conn->error . "\n";
}
$stmt->close();

$q = $conn->query("SELECT email, phone, address FROM members WHERE member_id = $member_id");
print_r($q->fetch_assoc());
?>
