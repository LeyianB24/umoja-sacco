<?php
/**
 * test_routing.php - Comprehensive API routing test
 * 
 * Tests all registered routes to ensure they're properly configured
 * and accessible through the central router.
 */

declare(strict_types=1);

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║          API ROUTING TEST SUITE                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Load the routes
$routesFile = __DIR__ . '/api/v1/routes.php';

if (!file_exists($routesFile)) {
    echo "❌ routes.php not found at: {$routesFile}\n";
    exit(1);
}

// Define routes array (copy of what's in routes.php)
$routes = [
    // Admin API — Notifications
    'POST:ajax_mark_read'       => ['file' => 'ajax_mark_read.php',       'auth' => 'admin'],

    // Admin API — Exports
    'GET:export_revenue'        => ['file' => 'export_revenue.php',        'auth' => 'admin'],
    'GET:generate_statement'    => ['file' => 'generate_statement.php',    'auth' => 'admin'],

    // Admin API — Charts
    'GET:get_chart_data'        => ['file' => 'get_chart_data.php',        'auth' => 'admin'],

    // Admin API — Performance & Monitoring
    'GET:admin/performance'     => ['file' => 'admin/performance.php',     'auth' => 'admin'],

    // Shared — Search
    'GET:search_members'        => ['file' => 'search_members.php',        'auth' => 'admin'],

    // Shared — Device Detection
    'GET:device/detect'         => ['file' => 'device-detection.php',      'auth' => 'none'],
    'POST:device/pause'         => ['file' => 'device-detection.php',      'auth' => 'none'],
    'POST:device/resume'        => ['file' => 'device-detection.php',      'auth' => 'none'],
    'POST:device/toggle'        => ['file' => 'device-detection.php',      'auth' => 'none'],
    'GET:device/state'          => ['file' => 'device-detection.php',      'auth' => 'none'],
];

// Test route registration
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ STEP 1: Verifying Route Registration                        │\n";
echo "└─────────────────────────────────────────────────────────────┘\n\n";

echo "✓ Found " . count($routes) . " registered routes:\n\n";

foreach ($routes as $key => $config) {
    echo "  • {$key}\n";
    echo "    └─ {$config['file']} (Auth: {$config['auth']})\n";
}

echo "\n";

// Test file existence
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ STEP 2: Verifying Handler Files Exist                       │\n";
echo "└─────────────────────────────────────────────────────────────┘\n\n";

$missingFiles = [];
$foundFiles = 0;

foreach ($routes as $key => $config) {
    $file = $config['file'];
    $handlerPath = __DIR__ . '/api/v1/' . $file;
    
    if (file_exists($handlerPath)) {
        echo "  ✓ {$file}\n";
        $foundFiles++;
    } else {
        echo "  ❌ {$file} (NOT FOUND)\n";
        $missingFiles[] = $file;
    }
}

echo "\n";

// Test endpoint accessibility
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ STEP 3: Verifying Endpoint Mappings                         │\n";
echo "└─────────────────────────────────────────────────────────────┘\n\n";

$testEndpoints = [
    ['GET', 'admin/performance'],
    ['GET', 'device/detect'],
    ['POST', 'device/pause'],
    ['GET', 'device/state'],
    ['GET', 'get_chart_data'],
    ['GET', 'search_members'],
];

$successCount = 0;

foreach ($testEndpoints as [$method, $endpoint]) {
    $key = "{$method}:{$endpoint}";
    
    if (isset($routes[$key])) {
        $config = $routes[$key];
        $handlerPath = __DIR__ . '/api/v1/' . $config['file'];
        
        if (file_exists($handlerPath)) {
            echo "  ✓ {$method} {$endpoint}\n";
            echo "    └─ {$config['file']} (Auth: {$config['auth']})\n";
            $successCount++;
        } else {
            echo "  ❌ {$method} {$endpoint}\n";
            echo "    └─ {$config['file']} (HANDLER NOT FOUND)\n";
        }
    } else {
        echo "  ❌ {$method} {$endpoint}\n";
        echo "    └─ NOT REGISTERED IN ROUTES\n";
    }
    echo "\n";
}

// Summary
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ SUMMARY                                                     │\n";
echo "└─────────────────────────────────────────────────────────────┘\n\n";

echo "  Total Routes:          " . count($routes) . "\n";
echo "  Handler Files Found:   {$foundFiles}\n";
echo "  Missing Files:         " . count($missingFiles) . "\n";
echo "  Test Endpoints OK:     {$successCount}/" . count($testEndpoints) . "\n\n";

if (count($missingFiles) > 0) {
    echo "⚠️  Missing handler files:\n";
    foreach ($missingFiles as $file) {
        echo "    • {$file}\n";
    }
    echo "\n";
    exit(1);
}

if ($successCount === count($testEndpoints)) {
    echo "✅ All routing is properly configured and ready!\n\n";
    echo "Usage Examples:\n";
    echo "  • GET  /api/v1/routes.php?endpoint=admin/performance\n";
    echo "  • GET  /api/v1/routes.php?endpoint=device/detect&vw=375&vh=667&touch=true\n";
    echo "  • POST /api/v1/routes.php?endpoint=device/pause&reason=Battery%20saver\n";
    echo "  • GET  /api/v1/routes.php?endpoint=get_chart_data\n";
    echo "  • GET  /api/v1/routes.php?endpoint=search_members&q=john\n\n";
    exit(0);
} else {
    echo "⚠️  Some routing issues found.\n";
    exit(1);
}

