<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use Throwable;
use PDO;

/**
 * USMS\Services\AuditService
 * Centralized Audit Logging System.
 */
class AuditService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Standard Log entry — callable as instance OR statically.
     * Static call: AuditService::log($conn_or_null, $action, $details, ...)
     * Instance call: (new AuditService())->log($action, $details, ...)
     */
    public static function log(mixed ...$args): void {
        // Detect legacy static call signature:
        // log($conn, $action, $details, $member_id, $admin_id, $severity)
        // vs instance/new signature:
        // log($action, $details, $member_id, $admin_id, $severity)
        if (isset($args[0]) && !is_string($args[0])) {
            // First arg is a DB connection object (legacy call) — skip it
            array_shift($args);
        }
        [$action, $details, $member_id, $admin_id, $severity] = array_pad($args, 5, null);
        (new self())->logEntry(
            (string)($action  ?? ''),
            (string)($details ?? ''),
            isset($member_id) ? (int)$member_id : null,
            isset($admin_id)  ? (int)$admin_id  : null,
            (string)($severity ?? 'info')
        );
    }

    /**
     * Core instance log writer.
     */
    public function logEntry(string $action, string $details, ?int $member_id = null, ?int $admin_id = null, string $severity = 'info'): void {
        try {
            $user_id   = $member_id ?: ($admin_id ?: 0);
            $user_type = $member_id ? 'member' : ($admin_id ? 'admin' : 'system');
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            $sql = "INSERT INTO audit_logs (action, details, member_id, admin_id, user_id, user_type, severity, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$action, $details, $member_id, $admin_id, $user_id, $user_type, $severity, $ip, $ua]);
        } catch (Throwable $e) {
            error_log("Audit Logging Failed: " . $e->getMessage());
        }
    }

    /**
     * Security specific events
     */
    public function security(string $details, ?int $member_id = null, ?int $admin_id = null): void {
        $this->logEntry('SECURITY_EVENT', $details, $member_id, $admin_id, 'critical');
    }

    /**
     * Financial specific events
     */
    public function financial(string $details, ?int $member_id = null, ?int $admin_id = null): void {
        $this->logEntry('FINANCIAL_EVENT', $details, $member_id, $admin_id, 'warning');
    }

    /**
     * Static gateway for convenience (migration helper)
     */
    public static function quickLog(string $action, string $details, ?int $member_id = null, ?int $admin_id = null, string $severity = 'info'): void {
        (new self())->logEntry($action, $details, $member_id, $admin_id, $severity);
    }
}
