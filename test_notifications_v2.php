<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
require_once 'c:/xampp/htdocs/usms/inc/notification_helpers.php';

// Mock a payment request for member 8 (Bezalel Tomaka)
$member_id = 8;
$data = [
    'amount' => 1200.00,
    'ref' => 'PAY-TEST-999',
    'trx_id' => 'PAY-TEST-999'
];

echo "Simulating payment_request notification for member $member_id...\n";
// Note: We use a try-catch because it might fail if SMTP is not really sending but should still queue/log
try {
    $success = send_notification($conn, $member_id, 'payment_request', $data);
    echo "Notification return status: " . ($success ? "TRUE" : "FALSE") . "\n";
} catch (Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
}

// Check the notifications table for the last entry to see the metadata and content
$res = $conn->query("SELECT title, message, metadata FROM notifications ORDER BY notification_id DESC LIMIT 1");
$row = $res->fetch_assoc();
echo "\nLast Notification Logged:\n";
echo "Title: " . $row['title'] . "\n";
echo "Metadata: " . $row['metadata'] . "\n";
?>
