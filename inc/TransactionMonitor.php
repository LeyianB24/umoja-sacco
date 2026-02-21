<?php
declare(strict_types=1);
/**
 * Transaction Monitor Service
 * Core logic for detecting and managing stuck or failed transactions
 */

class TransactionMonitor {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Get contributions that have been 'pending' for too long
     */
    public function getStuckPending($minutes = 5) {
        $stmt = $this->conn->prepare("
            SELECT c.*, m.full_name, m.phone, m.email, r.checkout_request_id, r.merchant_request_id
            FROM contributions c
            JOIN members m ON c.member_id = m.member_id
            LEFT JOIN mpesa_requests r ON c.reference_no = r.reference_no
            WHERE c.status = 'pending'
            AND c.created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY c.created_at DESC
        ");
        $stmt->bind_param("i", $minutes);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get recent callback logs for a specific request
     */
    public function getCallbackLogs($checkout_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM callback_logs 
            WHERE checkout_request_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("s", $checkout_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Create an alert for a stuck transaction
     */
    public function createAlert($type, $severity, $contribution_id, $message) {
        // Check if alert already exists for this contribution
        $check = $this->conn->prepare("SELECT alert_id FROM transaction_alerts WHERE contribution_id = ? AND acknowledged = 0");
        $check->bind_param("i", $contribution_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) return false;
        
        $stmt = $this->conn->prepare("
            INSERT INTO transaction_alerts (alert_type, severity, contribution_id, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssis", $type, $severity, $contribution_id, $message);
        return $stmt->execute();
    }
    
    /**
     * Run a health check and generate alerts
     */
    public function runHealthCheck() {
        $stuck = $this->getStuckPending(5);
        $alertCount = 0;
        
        foreach ($stuck as $s) {
            $msg = "Transaction KES " . number_format($s['amount'], 2) . " for " . $s['full_name'] . " has been pending since " . $s['created_at'];
            if ($this->createAlert('stuck_pending', 'warning', $s['contribution_id'], $msg)) {
                $alertCount++;
            }
        }
        
        return $alertCount;
    }
    
    /**
     * Get active alerts
     */
    public function getActiveAlerts() {
        $result = $this->conn->query("
            SELECT a.*, c.amount, c.contribution_type, m.full_name
            FROM transaction_alerts a
            JOIN contributions c ON a.contribution_id = c.contribution_id
            JOIN members m ON a.member_id = m.member_id
            WHERE a.acknowledged = 0
            ORDER BY a.created_at DESC
        ");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
