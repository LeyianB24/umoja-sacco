<?php
declare(strict_types=1);

namespace USMS\Database;

/**
 * QueryLogger tracks all database queries for performance monitoring and debugging.
 * Helps identify N+1 queries, slow queries, and optimization opportunities.
 */
class QueryLogger
{
    private static array $queries = [];
    private static bool $enabled = true;
    private static float $slowQueryThreshold = 1.0; // seconds
    private static string $logFile;
    
    public static function initialize(string $logDir = __DIR__ . '/../../storage/logs'): void
    {
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        self::$logFile = $logDir . '/queries_' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log a query with execution time
     */
    public static function log(string $sql, array $params = [], float $executionTime = 0, bool $isError = false): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $entry = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $executionTime,
            'is_error' => $isError,
            'timestamp' => time(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
        
        self::$queries[] = $entry;
        
        // Log to file if slow query
        if ($executionTime > self::$slowQueryThreshold) {
            self::writeToFile($entry);
        }
    }
    
    /**
     * Get all logged queries
     */
    public static function getAll(): array
    {
        return self::$queries;
    }
    
    /**
     * Get query statistics
     */
    public static function getStats(): array
    {
        $stats = [
            'total_queries' => count(self::$queries),
            'total_time' => 0,
            'slow_queries' => 0,
            'errors' => 0,
            'duplicate_queries' => 0,
            'avg_time' => 0
        ];
        
        $queryPatterns = [];
        
        foreach (self::$queries as $query) {
            $stats['total_time'] += $query['execution_time'];
            
            if ($query['execution_time'] > self::$slowQueryThreshold) {
                $stats['slow_queries']++;
            }
            
            if ($query['is_error']) {
                $stats['errors']++;
            }
            
            // Detect similar queries (potential N+1)
            $pattern = preg_replace('/\d+/', '?', $query['sql']);
            $queryPatterns[$pattern] = ($queryPatterns[$pattern] ?? 0) + 1;
        }
        
        $stats['avg_time'] = $stats['total_queries'] > 0 
            ? $stats['total_time'] / $stats['total_queries'] 
            : 0;
        
        foreach ($queryPatterns as $count) {
            if ($count > 1) {
                $stats['duplicate_queries']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Detect N+1 query problems
     */
    public static function detectN1Problems(): array
    {
        $patterns = [];
        
        foreach (self::$queries as $query) {
            $pattern = preg_replace('/\d+/', '?', $query['sql']);
            
            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = [
                    'count' => 0,
                    'sql' => $query['sql'],
                    'total_time' => 0,
                    'examples' => []
                ];
            }
            
            $patterns[$pattern]['count']++;
            $patterns[$pattern]['total_time'] += $query['execution_time'];
            
            if (count($patterns[$pattern]['examples']) < 3) {
                $patterns[$pattern]['examples'][] = [
                    'sql' => $query['sql'],
                    'time' => $query['execution_time']
                ];
            }
        }
        
        // Return only potential N+1 patterns (executed 3+ times)
        $n1Problems = [];
        foreach ($patterns as $pattern => $data) {
            if ($data['count'] >= 3) {
                $n1Problems[] = $data;
            }
        }
        
        usort($n1Problems, fn($a, $b) => $b['total_time'] <=> $a['total_time']);
        return $n1Problems;
    }
    
    /**
     * Get slowest queries
     */
    public static function getSlowestQueries(int $limit = 10): array
    {
        $queries = self::$queries;
        usort($queries, fn($a, $b) => $b['execution_time'] <=> $a['execution_time']);
        return array_slice($queries, 0, $limit);
    }
    
    /**
     * Enable/disable logging
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
    
    /**
     * Clear all logged queries
     */
    public static function clear(): void
    {
        self::$queries = [];
    }
    
    /**
     * Set slow query threshold (in seconds)
     */
    public static function setSlowQueryThreshold(float $seconds): void
    {
        self::$slowQueryThreshold = $seconds;
    }
    
    /**
     * Write query to log file
     */
    private static function writeToFile(array $entry): void
    {
        if (!self::$logFile) {
            return;
        }
        
        $logEntry = sprintf(
            "[%s] SLOW QUERY (%.3fs)\n%s\nParams: %s\n%s\n",
            date('Y-m-d H:i:s', $entry['timestamp']),
            $entry['execution_time'],
            $entry['sql'],
            json_encode($entry['params']),
            str_repeat('-', 80)
        );
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Export query log as JSON
     */
    public static function export(): array
    {
        return [
            'queries' => self::$queries,
            'stats' => self::getStats(),
            'n1_problems' => self::detectN1Problems(),
            'slowest' => self::getSlowestQueries()
        ];
    }
}
