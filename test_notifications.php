<?php
// test_notifications.php
// Verification script for unified notifications

require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/notification_helpers.php';

echo "<h2>Notification System Verification</h2>";

// Test Member ID (ensure this exists or use a dummy)
$test_member_id = 1; 

$test_types = [
    'registration_success' => ['member_no' => 'USR-1001'],
    'profile_updated'     => [],
    'withdrawal_request'  => ['amount' => 5000, 'ref' => 'TEST-WD-001'],
    'payment_request'     => ['amount' => 1200, 'ref' => 'TEST-PAY-001'],
    'loan_approved'       => ['amount' => 50000, 'ref' => 'TEST-LOAN-001'],
    'loan_rejected'       => ['amount' => 50000, 'rejection_reason' => 'Insufficient guarantors', 'ref' => 'TEST-LOAN-001'],
    'loan_disbursed'      => ['amount' => 50000, 'ref' => 'TEST-LOAN-001'],
    'deposit_success'     => ['amount' => 2000, 'ref' => 'TEST-DEP-001']
];

foreach ($test_types as $type => $data) {
    echo "Processing <strong>$type</strong>... ";
    try {
        $success = send_notification($conn, $test_member_id, $type, $data);
        if ($success) {
            echo "<span style='color:green;'>SUCCESS</span><br>";
        } else {
            echo "<span style='color:red;'>FAILED (Check logs)</span><br>";
        }
    } catch (Throwable $e) {
        echo "<span style='color:red;'>ERROR: " . $e->getMessage() . "</span><br>";
    }
}

echo "<br><b>Database Check:</b><br>";
$res = $conn->query("SELECT title, comms_type, delivery_status FROM notifications ORDER BY created_at DESC LIMIT 8");
while ($row = $res->fetch_assoc()) {
    echo "Title: {$row['title']} | Type: {$row['comms_type']} | Status: {$row['delivery_status']}<br>";
}
?>
