<?php
/**
 * Root Request Dispatcher
 * Routes API requests to backend/api/v1/ if invoked from root server
 */

$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH) ?? '';

if (str_starts_with($path, '/api/v1/')) {
    require __DIR__ . '/backend/api/v1/index.php';
    exit;
}

// Redirect web browser to Next.js frontend or backend public
if (file_exists(__DIR__ . '/backend/public/index.php')) {
    header("Location: backend/public/index.php");
    exit;
}

header("Location: /");
exit;
