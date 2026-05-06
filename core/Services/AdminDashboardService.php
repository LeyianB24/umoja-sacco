<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Cache\CacheManager;
use USMS\Database\QueryLogger;

/**
 * AdminDashboardService provides optimized dashboard metrics.
 * Batches queries and uses caching to eliminate N+1 problems.
 */
class AdminDashboardService
{
    private \mysqli $conn;
    private CacheManager $cache;
    private int $cacheExpiry = 300; // 5 minutes
    
    public function __construct(\mysqli $conn, CacheManager $cache = null)
    {
        $this->conn = $conn;
        $this->cache = $cache ?? new CacheManager();
        QueryLogger::initialize();
    }
    
    /**
     * Get comprehensive dashboard metrics in a single operation
     */
    public function getDashboardMetrics(int $roleId = 1): array
    {
        $cacheKey = "admin_dashboard_metrics_role_{$roleId}";
        
        return $this->cache->remember($cacheKey, function () use ($roleId) {
            $startTime = microtime(true);
            
            // Batch all queries
            $metrics = [
                'support_tickets' => $this->getSupportTickets($roleId),
                'member_stats' => $this->getMemberStats(),
                'loan_metrics' => $this->getLoanMetrics(),
                'cash_position' => $this->getCashPosition(),
                'database_health' => $this->getDatabaseHealth(),
                'revenue_trend' => $this->getRevenueTrend(),
                'system_status' => $this->getSystemStatus(),
            ];
            
            $metrics['performance'] = [
                'execution_time' => microtime(true) - $startTime,
                'cache_hit' => false
            ];
            
            return $metrics;
        }, $this->cacheExpiry);
    }
    
    /**
     * Get support tickets count by role
     */
    private function getSupportTickets(int $roleId = 1): int
    {
        $query = "SELECT COUNT(*) AS c FROM support_tickets WHERE status != 'Closed'";
        
        if ($roleId !== 1) {
            $query .= " AND assigned_role_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $roleId);
        } else {
            $stmt = $this->conn->prepare($query);
        }
        
        QueryLogger::log($query, [$roleId]);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['c'] ?? 0);
    }
    
    /**
     * Get member statistics (total, active, inactive, suspended)
     */
    private function getMemberStats(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
            FROM members
        ";
        
        QueryLogger::log($query);
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'active' => (int)($result['active'] ?? 0),
            'inactive' => (int)($result['inactive'] ?? 0),
            'suspended' => (int)($result['suspended'] ?? 0),
        ];
    }
    
    /**
     * Get loan metrics (pending, approved, disbursed, exposure)
     */
    private function getLoanMetrics(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) as disbursed,
                SUM(CASE WHEN status IN ('pending', 'approved', 'disbursed') THEN current_balance ELSE 0 END) as exposure
            FROM loans
        ";
        
        QueryLogger::log($query);
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'pending' => (int)($result['pending'] ?? 0),
            'approved' => (int)($result['approved'] ?? 0),
            'disbursed' => (int)($result['disbursed'] ?? 0),
            'total_exposure' => (float)($result['exposure'] ?? 0),
        ];
    }
    
    /**
     * Get cash position (liquidity)
     */
    private function getCashPosition(): float
    {
        $query = "
            SELECT SUM(current_balance) as balance 
            FROM ledger_accounts 
            WHERE category IN ('cash', 'bank', 'mpesa')
        ";
        
        QueryLogger::log($query);
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (float)($result['balance'] ?? 0);
    }
    
    /**
     * Get database health metrics
     */
    private function getDatabaseHealth(): array
    {
        $query = "SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema=DATABASE()";
        
        QueryLogger::log($query);
        $result = $this->conn->query($query)->fetch_assoc();
        
        return [
            'database_size_mb' => round((float)($result['size'] ?? 0), 2),
            'last_backup' => $this->getLastBackupTime(),
            'table_count' => $this->getTableCount(),
        ];
    }
    
    /**
     * Get revenue trend for last 7 days
     */
    private function getRevenueTrend(): array
    {
        $query = "
            SELECT 
                DATE(t.created_at) as date, 
                SUM(e.credit) as revenue 
            FROM ledger_entries e
            JOIN ledger_transactions t ON e.transaction_id = t.transaction_id
            JOIN ledger_accounts a ON e.account_id = a.account_id
            WHERE a.account_type = 'revenue' 
            AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(t.created_at)
            ORDER BY date ASC
        ";
        
        QueryLogger::log($query);
        $result = $this->conn->query($query);
        
        $trend = [];
        $labels = [];
        $data = [];
        
        // Initialize 7 days with zeros
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trend[$date] = 0;
            $labels[] = date('D, M j', strtotime($date));
        }
        
        // Fill in actual data
        while ($row = $result->fetch_assoc()) {
            $trend[$row['date']] = (float)$row['revenue'];
        }
        
        // Convert to arrays for chart
        foreach ($trend as $revenue) {
            $data[] = $revenue;
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'total' => array_sum($data)
        ];
    }
    
    /**
     * Get system status
     */
    private function getSystemStatus(): array
    {
        return [
            'database_connected' => $this->conn->ping() ? true : false,
            'queue_pending' => $this->getQueueCount('pending'),
            'emails_pending' => $this->getQueueCount('email'),
            'system_health' => 'healthy'
        ];
    }
    
    /**
     * Get last backup timestamp
     */
    private function getLastBackupTime(): string
    {
        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) {
            return 'Never';
        }
        
        $files = glob($backupDir . '/USMS_Backup_*.sql');
        if (empty($files)) {
            return 'Never';
        }
        
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $mtime = filemtime($files[0]);
        $hours = (time() - $mtime) / 3600;
        
        if ($hours < 1) {
            return 'Just now';
        } elseif ($hours < 24) {
            return round($hours) . 'h ago';
        } else {
            return round($hours / 24) . 'd ago';
        }
    }
    
    /**
     * Get table count
     */
    private function getTableCount(): int
    {
        $result = $this->conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE table_schema=DATABASE()");
        return (int)($result->fetch_assoc()['c'] ?? 0);
    }
    
    /**
     * Get pending queue count
     */
    private function getQueueCount(string $type): int
    {
        $tables = [
            'pending' => 'job_queue',
            'email' => 'email_queue'
        ];
        
        if (!isset($tables[$type])) {
            return 0;
        }
        
        $result = $this->conn->query("SELECT COUNT(*) as c FROM {$tables[$type]} WHERE status = 'pending'");
        return (int)($result->fetch_assoc()['c'] ?? 0);
    }
    
    /**
     * Invalidate dashboard cache
     */
    public function invalidateCache(): void
    {
        $this->cache->delete("admin_dashboard_metrics_role_1");
        for ($i = 2; $i <= 10; $i++) {
            $this->cache->delete("admin_dashboard_metrics_role_{$i}");
        }
    }
}
