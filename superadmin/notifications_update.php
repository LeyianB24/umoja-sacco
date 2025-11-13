<?php
// notifications_update.php
// Handles marking notifications as read or deleting them

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Only superadmin can update notifications in this section
require_superadmin();

$db = $conn;

$action = $_GET['action'] ?? '';
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notification_id < 1) {
    die("Invalid notification ID.");
}

// ----------------------------------------------------------------------
// 1. MARK AS READ
// ----------------------------------------------------------------------
if ($action === 'mark_read') {

    $sql = "UPDATE notifications SET status='read', is_read=1 WHERE notification_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();

    header("Location: notifications.php?msg=marked");
    exit;
}

// ----------------------------------------------------------------------
// 2. DELETE NOTIFICATION
// ----------------------------------------------------------------------
if ($action === 'delete') {

    $sql = "DELETE FROM notifications WHERE notification_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();

    header("Location: notifications.php?msg=deleted");
    exit;
}

// ----------------------------------------------------------------------
// 3. INVALID ACTION
// ----------------------------------------------------------------------
die("Invalid action.");