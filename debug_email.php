<?php
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/email.php';

echo "Database connection check: " . (isset($conn) ? "OK" : "FAILED") . "\n";
if (isset($conn)) {
    echo "Host info: " . $conn->host_info . "\n";
}

// Get a valid member ID
$result = $conn->query("SELECT id FROM members LIMIT 1");
$member_id = 1; // Default
if ($result && $row = $result->fetch_assoc()) {
    $member_id = $row['id'];
}
echo "Using Member ID: $member_id\n";

echo "Testing sendEmail...\n";
$test_email = "leyianbeza24@gmail.com"; 
$res = sendEmail($test_email, "System Test (Valid ID)", "This is a direct test with MemberID: $member_id", $member_id);
echo "Result: " . ($res ? "SUCCESS" : "FAILED") . "\n";
