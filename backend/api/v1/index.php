<?php
declare(strict_types=1);

/**
 * api/v1/index.php & api/v1/routes.php
 * Unified router for USMS REST API v1
 */

require_once __DIR__ . '/cors.php';

// Extract requested path or query action
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH) ?? '';

// Check query param 'endpoint' or 'action' or 'path'
$action = $_GET['endpoint'] ?? $_GET['action'] ?? $_GET['path'] ?? '';

if (empty($action)) {
    // Attempt extracting from PATH after /api/v1/
    if (preg_match('#/api/v1/([a-zA-Z0-9_\-\/]+)#', $path, $matches)) {
        $action = trim($matches[1], '/');
    }
}

// Router mapping
$segments = explode('/', $action);
$module   = $segments[0] ?? '';
$subaction = $segments[1] ?? '';

if ($module === 'auth') {
    $_GET['action'] = $subaction ?: 'login';
    require __DIR__ . '/auth.php';
    exit;
}

if ($module === 'member') {
    $_GET['action'] = $subaction ?: 'dashboard';
    require __DIR__ . '/member_api.php';
    exit;
}

if ($module === 'admin') {
    $_GET['action'] = $subaction ?: 'dashboard';
    require __DIR__ . '/admin_api.php';
    exit;
}

if ($module === 'public') {
    $_GET['action'] = $subaction ?: 'stats';
    require __DIR__ . '/public_api.php';
    exit;
}

// Default fallback to legacy router / routes
if (file_exists(__DIR__ . '/' . $action . '.php')) {
    require __DIR__ . '/' . $action . '.php';
    exit;
}

api_error("Endpoint '{$action}' not found in API v1.", 404);
