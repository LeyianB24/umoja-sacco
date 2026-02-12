<?php
declare(strict_types=1);

class AuditHelper {
    public static function log(mysqli $conn, string $action, string $details, ?int $member_id = null, ?int $admin_id = null, string $severity = 'info'): void {
        try {
            $user_id = $member_id ?: ($admin_id ?: 0);
            $user_type = $member_id ? 'member' : ($admin_id ? 'admin' : 'system');
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            $sql = "INSERT INTO audit_logs (action, details, member_id, admin_id, user_id, user_type, severity, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssiiissss", $action, $details, $member_id, $admin_id, $user_id, $user_type, $severity, $ip, $ua);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log("Audit Logging Failed: " . $e->getMessage());
        }
    }

    public static function security(mysqli $conn, string $details, ?int $member_id = null, ?int $admin_id = null): void {
        self::log($conn, 'SECURITY_EVENT', $details, $member_id, $admin_id, 'critical');
    }

    public static function financial(mysqli $conn, string $details, ?int $member_id = null, ?int $admin_id = null): void {
        self::log($conn, 'FINANCIAL_EVENT', $details, $member_id, $admin_id, 'warning');
    }
}
