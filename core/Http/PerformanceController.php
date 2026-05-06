<?php
declare(strict_types=1);

namespace USMS\Http;

use USMS\Cache\CacheManager;
use USMS\Database\QueryLogger;
use USMS\Services\AdminDashboardService;

/**
 * Performance monitoring API endpoint
 * GET /api/v1/admin/performance
 */
class PerformanceController
{
    private \mysqli $conn;
    private CacheManager $cache;
    
    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
        $this->cache = new CacheManager();
        QueryLogger::initialize();
    }
    
    /**
     * Get performance metrics
     */
    public function index(): array
    {
        return [
            'dashboard_metrics' => $this->getDashboardMetrics(),
            'query_performance' => QueryLogger::getStats(),
            'cache_stats' => $this->cache->stats(),
            'n1_problems' => QueryLogger::detectN1Problems(),
            'slowest_queries' => QueryLogger::getSlowestQueries(5),
            'system_health' => $this->getSystemHealth(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get dashboard metrics
     */
    private function getDashboardMetrics(): array
    {
        $service = new AdminDashboardService($this->conn, $this->cache);
        return $service->getDashboardMetrics();
    }
    
    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        return [
            'database_connected' => $this->conn->ping(),
            'storage_available' => disk_free_space('/') / 1024 / 1024 / 1024, // GB
            'memory_usage' => memory_get_usage(true) / 1024 / 1024, // MB
            'uptime' => shell_exec('uptime -p') ?? 'unknown'
        ];
    }
}
