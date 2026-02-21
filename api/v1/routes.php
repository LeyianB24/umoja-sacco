<?php
declare(strict_types=1);
/**
 * api/v1/routes.php
 * USMS API v1 — Central Request Router
 *
 * Routes incoming requests to the appropriate handler script.
 * All API responses must follow the standard JSON envelope:
 *   { "status": "success"|"error", "data": ..., "message": "..." }
 *
 * Usage: All API calls should go through this file.
 * Direct file access is still allowed for backwards compat, but
 * this router is the canonical entry point.
 *
 * Add new routes in the $routes map below.
 */

namespace USMS\Http;

// ── Bootstrap ────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';

// ── CORS / JSON Headers ───────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Standard Response Helpers ─────────────────────────────────────────────────
function api_success(mixed $data = null, string $message = 'OK', int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(string $message, int $code = 400, mixed $data = null): never
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Route Registry ────────────────────────────────────────────────────────────
// Format: 'METHOD:endpoint' => ['file' => 'path/to/handler.php', 'auth' => 'admin'|'member'|'none']
$routes = [
    // Admin API — Notifications
    'POST:ajax_mark_read'       => ['file' => 'ajax_mark_read.php',       'auth' => 'admin'],

    // Admin API — Exports
    'GET:export_revenue'        => ['file' => 'export_revenue.php',        'auth' => 'admin'],
    'GET:generate_statement'    => ['file' => 'generate_statement.php',    'auth' => 'admin'],

    // Admin API — Charts
    'GET:get_chart_data'        => ['file' => 'get_chart_data.php',        'auth' => 'admin'],

    // Shared — Search
    'GET:search_members'        => ['file' => 'search_members.php',        'auth' => 'admin'],
];

// ── Resolve the Incoming Request ──────────────────────────────────────────────
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$endpoint = $_GET['action'] ?? $_GET['endpoint'] ?? '';

if (empty($endpoint)) {
    api_error('Missing endpoint parameter.', 400);
}

$key = $method . ':' . $endpoint;

if (!isset($routes[$key])) {
    api_error('Unknown endpoint: ' . htmlspecialchars($endpoint), 404);
}

$route = $routes[$key];

// ── Auth Guard ────────────────────────────────────────────────────────────────
match ($route['auth']) {
    'admin'  => Auth::requireAdmin(),
    'member' => (function () {
        if (!isset($_SESSION['member_id'])) {
            api_error('Unauthorized', 401);
        }
    })(),
    default  => null,
};

// ── Dispatch ──────────────────────────────────────────────────────────────────
$handlerPath = __DIR__ . '/' . $route['file'];

if (!file_exists($handlerPath)) {
    api_error('Handler not found for endpoint: ' . htmlspecialchars($endpoint), 500);
}

// Make helpers available to the handler
$GLOBALS['api_success'] = 'api_success';
$GLOBALS['api_error']   = 'api_error';

require $handlerPath;
exit;
