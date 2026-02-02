<?php
// public/ajax_mark_read.php
// Handles marking messages and notifications as read for both Admins and Members

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

// Determine user
$user_id = 0;
$user_type = '';

if (isset($_SESSION['member_id'])) {
    $user_id = $_SESSION['member_id'];
    $user_type = 'member';
} elseif (isset($_SESSION['admin_id'])) {
    $user_id = $_SESSION['admin_id'];
    $user_type = 'admin'; // 'admin', 'manager', etc. are all in 'admins' table and handled as 'admin' type for notifications usually
    // However, notification table might use specific roles. 
    // TopbarHelper uses $role variable from session for fetching.
    // Let's check session role for uniformity if needed, but 'admin' covers the table-side usually.
    // The query in TopbarHelper uses: WHERE user_type=? (which comes from $_SESSION['role'])
    // So we should probably use the session role or 'admin'. 
    // Let's stick to what SESSION role says if it's not member.
    $user_type = $_SESSION['role'] ?? 'admin';
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

if ($type === 'message') {
    // Mark message read
    // Messages table usually has to_member_id and to_admin_id
    if (isset($_SESSION['member_id'])) {
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND to_member_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND to_admin_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
    }
    
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }

} elseif ($type === 'notification') {
    // Mark notification read
    // Notifications table has user_id and user_type
    // We must match the user_type stored in DB.
    // If the topbar fetches using $_SESSION['role'], we should use that too.
    
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ? AND user_id = ? AND user_type = ?");
    $stmt->bind_param("iis", $id, $user_id, $user_type);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['status' => 'success']);
?>
