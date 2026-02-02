<?php
// admin/api/ajax_mark_read.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/notification_helpers.php';
require_once __DIR__ . '/../../inc/MessageHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['admin_id'];
$role = $_SESSION['role'] ?? 'admin';
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? 'read';

if ($type === 'message' && intval($id) > 0) {
    echo json_encode(['success' => MessageHelper::markRead($conn, intval($id), $user_id, 'admin')]);
} 
elseif ($type === 'notification') {
    $notif_id = ($action === 'clear_all' || $id === 'all') ? 'all' : intval($id);
    echo json_encode(['success' => mark_notification_read($conn, $notif_id, $user_id, $role)]);
} 
else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>