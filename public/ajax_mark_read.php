<?php
// public/ajax_mark_read.php
require_once __DIR__ . '/../../config/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['member_id'])) {
    
    $type = $_POST['type'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $member_id = $_SESSION['member_id'];

    if ($id > 0) {
        if ($type === 'message') {
            // Mark specific message as read
            $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND (to_member_id = ? OR to_admin_id IS NOT NULL)");
            // Note: Adjust logic if your message system works differently
            $stmt->bind_param("ii", $id, $member_id);
            $stmt->execute();
        } 
        elseif ($type === 'notification') {
            // Mark notification as read
            $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE id = ? AND member_id = ?");
            $stmt->bind_param("ii", $id, $member_id);
            $stmt->execute();
        }
    }
}
?>