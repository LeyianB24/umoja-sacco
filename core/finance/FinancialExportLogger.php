<?php
// core/finance/FinancialExportLogger.php
require_once __DIR__ . '/../../config/db_connect.php';

class FinancialExportLogger {
    
    /**
     * Log a financial export attempt
     * 
     * @param string $module The financial module (Savings, Loans, etc.)
     * @param string $format PDF or Excel
     * @param int $recordCount Number of records exported
     * @param float $totalValue Total monetary value in the export
     * @return int|false The export ID on success, or false on failure
     */
    public static function log($module, $format, $recordCount, $totalValue) {
        global $conn;
        
        // Resolve User ID & Role
        $userId = $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? null);
        $userRole = 'System';
        
        if (isset($_SESSION['role_name'])) {
            $userRole = $_SESSION['role_name'];
        } elseif (isset($_SESSION['role'])) {
            $userRole = $_SESSION['role']; // e.g., 'superadmin'
        } elseif (isset($_SESSION['member_name'])) {
            $userRole = 'Member';
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $status = 'success'; // Optimistic log, update on failure implementation if needed

        // Prepare SQL
        $sql = "INSERT INTO financial_export_logs 
                (user_id, user_role, financial_module, export_format, export_date, ip_address, record_count, total_value, status) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            // Log error to system log if possible
            error_log("Financial Export Logger Prepare Failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("issssids", $userId, $userRole, $module, $format, $ip, $recordCount, $totalValue, $status);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
        } else {
            error_log("Financial Export Logger Execute Failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}
?>
