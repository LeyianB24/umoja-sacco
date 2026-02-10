<?php
require_once __DIR__ . '/inc/email.php';

// Test insert only (mocking email failure by disconnect/etc? No, just testing the function logic)
// actually, let's just copy the insert logic here to test it isolated.

require_once __DIR__ . '/config/db_connect.php';

echo "Testing Notification Insert...\n";
$member_id = 1;
$admin_id = null;
$user_id = 1;
$user_type = 'member';
$to_role = 'member';
$subject = 'Test Notification';
$plain_message = 'This is a test.';

$sql = "INSERT INTO notifications 
        (member_id, admin_id, user_id, user_type, to_role, title, message, status, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'unread', 0, NOW())";

try {
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iiissss", $member_id, $admin_id, $user_id, $user_type, $to_role, $subject, $plain_message);
        if ($stmt->execute()) {
            echo "Insert SUCCESS\n";
        } else {
            echo "Insert FAILED: " . $stmt->error . "\n";
        }
        $stmt->close();
    } else {
        echo "Prepare FAILED: " . $conn->error . "\n";
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
