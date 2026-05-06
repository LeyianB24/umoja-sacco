<?php
/**
 * Performance Monitoring API Endpoint
 * 
 * Usage: GET /api/v1/admin/performance
 * 
 * Returns comprehensive performance metrics including:
 * - Dashboard metrics (users, loans, cash position, etc.)
 * - Query statistics (total queries, execution time, slow queries)
 * - Cache efficiency stats
 * - N+1 query problems detection
 * - System health (database, storage, memory)
 */

declare(strict_types=1);

session_start();

// Verify admin access
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../../../config/app.php';
    require_once __DIR__ . '/../../../config/db_connect.php';
    
    use USMS\Http\PerformanceController;
    use USMS\Cache\CacheManager;
    use USMS\Database\QueryLogger;
    
    // Initialize query logging
    QueryLogger::initialize();
    
    // Create controller instance
    $controller = new PerformanceController($conn);
    
    // Get metrics
    $metrics = $controller->index();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}
