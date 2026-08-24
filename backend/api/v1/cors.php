<?php
declare(strict_types=1);

/**
 * api/v1/cors.php
 * Handles Cross-Origin Resource Sharing (CORS) and standard JSON response envelopes.
 */

// Allow credentials and specific origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:3001',
    'http://localhost:8000',
];

if (in_array($origin, $allowed_origins, true) || empty($origin)) {
    if (!empty($origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header("Access-Control-Allow-Origin: *");
    }
} else {
    // In development allow the requesting origin if it is localhost or local network
    if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, Accept, Origin");
header("Access-Control-Max-Age: 86400");
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Helper to get JSON payload
 */
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return $_POST;
        $data = json_decode($raw, true);
        return is_array($data) ? array_merge($_POST, $data) : $_POST;
    }
}

/**
 * Standard success response
 */
if (!function_exists('api_success')) {
    function api_success(mixed $data = null, string $message = 'Success', int $code = 200): never {
        http_response_code($code);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * Standard error response
 */
if (!function_exists('api_error')) {
    function api_error(string $message = 'Error occurred', int $code = 400, mixed $data = null): never {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
