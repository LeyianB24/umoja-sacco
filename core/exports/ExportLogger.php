<?php
// core/exports/ExportLogger.php
require_once __DIR__ . '/../../config/db_connect.php';

class ExportLogger {
    public static function log($type, $module, $details = []) {
        global $conn;
        
        // Resolve User ID (Admin or Member)
        $userId = $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? null);
        
        // Resolve Role
        $userRole = 'Guest';
        if (isset($_SESSION['role_name'])) {
            $userRole = $_SESSION['role_name'];
        } elseif (isset($_SESSION['role'])) {
            $userRole = $_SESSION['role'];
        } elseif (isset($_SESSION['member_name'])) {
            $userRole = 'Member';
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $detailsJson = json_encode($details);
        
        $stmt = $conn->prepare("INSERT INTO export_logs (user_id, user_role, module, export_type, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssss", $userId, $userRole, $module, $type, $ip, $detailsJson);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        return false;
    }
}
?>
