<?php
declare(strict_types=1);
namespace USMS\Reports;

class ExportAuditLogger {
    
    public static function log($module, $format, $recordCount, $totalValue = 0.00, $details = null) {
        global $conn;

        try {
            $userId = $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? ($_SESSION['member_id'] ?? null));
            
            $userRole = 'System';
            if (isset($_SESSION['role_name'])) $userRole = $_SESSION['role_name'];
            elseif (isset($_SESSION['role'])) $userRole = $_SESSION['role'];
            elseif (isset($_SESSION['member_name'])) $userRole = 'Member';
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $status = 'success';
            
            $sql = "INSERT INTO export_logs 
                    (user_id, user_role, module, export_type, exported_at, ip_address, record_count, total_value, status, details) 
                    VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
                    
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Export Audit Log Failed (Prepare): " . $conn->error);
                return false;
            }

            $stmt->bind_param("issssidss", $userId, $userRole, $module, $format, $ip, $recordCount, $totalValue, $status, $details);
            
            if ($stmt->execute()) {
                $insertId = $stmt->insert_id;
                $stmt->close();
                return $insertId;
            }

            error_log("Export Audit Log Failed (Execute): " . $stmt->error);
            $stmt->close();
            return false;
        } catch (\Throwable $e) {
            error_log("Export Audit Log Failed: " . $e->getMessage());
            return false;
        }
    }
}

