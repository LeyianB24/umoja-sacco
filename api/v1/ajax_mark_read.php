<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/notification_helpers.php';
require_once __DIR__ . '/../../inc/auth.php';

use USMS\Http\ErrorHandler;
use USMS\Services\MessageService;

header('Content-Type: application/json');

// Check authentication manually to avoid HTML redirect in AJAX context
if (!isset($_SESSION['admin_id'])) {
    ErrorHandler::jsonError('Unauthorized access', 401);
}

$user_id = (int)$_SESSION['admin_id'];
$role    = $_SESSION['role'] ?? 'admin';
$type    = $_POST['type'] ?? '';
$id      = $_POST['id'] ?? 0;
$action  = $_POST['action'] ?? 'read';

try {
    if ($type === 'message' && (int)$id > 0) {
        $success = MessageService::quickMarkRead((int)$id, $user_id);
        echo json_encode(['success' => $success]);
    } elseif ($type === 'notification') {
        $notif_id = ($action === 'clear_all' || $id === 'all') ? 'all' : (int)$id;
        $success = mark_notification_read($conn, $notif_id, $user_id, (string)$role);
        echo json_encode(['success' => $success]);
    } else {
        ErrorHandler::jsonError('Invalid request type or missing parameters', 400);
    }
} catch (\Throwable $e) {
    ErrorHandler::jsonError($e->getMessage(), 500);
}

