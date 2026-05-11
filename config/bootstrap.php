<?php
/**
 * config/bootstrap.php
 * Application bootstrap and environment validation
 * Call this FIRST in your application entry point
 */

declare(strict_types=1);

// Start output buffering (essential for PDF generation and header redirects)
if (ob_get_level() === 0) ob_start();

require_once __DIR__ . '/EnvLoader.php';
use USMS\Config\EnvLoader;

// Load environment variables
EnvLoader::load();

// 1. BASE PATHS & URLS
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));

// Dynamic BASE_URL: detect if we are in a subdirectory or root
if (!defined('BASE_URL')) {
    $env_base_url = \USMS\Config\EnvLoader::get('BASE_URL');
    if ($env_base_url !== null) {
        define('BASE_URL', rtrim($env_base_url, '/'));
    } else {
        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        $detected_base = (stripos($req_uri, '/usms') === 0) ? '/usms' : '';
        define('BASE_URL', $detected_base);
    }
}

if (!defined('PUBLIC_URL')) {
    // Detect if we are already serving from the public directory (common in production)
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script_name, '/public/') === false && file_exists(BASE_PATH . '/public/index.php')) {
        // If /public/ is NOT in the URL but the folder exists, we might be serving from it.
        // Let's check the DOCUMENT_ROOT vs BASE_PATH.
        $doc_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $public_path = realpath(BASE_PATH . '/public');
        if ($doc_root && $public_path && strcasecmp($doc_root, $public_path) === 0) {
             // We are serving FROM the public directory as the root
             define('PUBLIC_URL', BASE_URL);
        } else {
             // We are serving from a parent directory, so we must include /public
             define('PUBLIC_URL', BASE_URL . '/public');
        }
    } else {
        // Fallback or explicit public in URL
        define('PUBLIC_URL', BASE_URL . (strpos($script_name, '/public/') !== false ? '/public' : '/public'));
    }
}
if (!defined('ASSET_BASE')) define('ASSET_BASE', rtrim(PUBLIC_URL, '/') . '/assets');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http');
$host     = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$site_url = $protocol . '://' . rtrim($host, '/');
if (!empty(BASE_URL)) {
    $site_url .= '/' . ltrim(BASE_URL, '/');
}
if (!defined('SITE_URL')) define('SITE_URL', rtrim($site_url, '/'));

/**
 * Validate that all critical environment variables are set
 * This prevents mysterious errors later due to missing config
 * Priority: MYSQL_* (Railway) > DB_* (Local Dev) > check for alternatives
 */
try {
    // Check if we have Railway MySQL variables OR local DB variables
    $has_mysql_vars = EnvLoader::has('MYSQLHOST') && EnvLoader::has('MYSQLUSER') && EnvLoader::has('MYSQLDATABASE');
    $has_db_vars = EnvLoader::has('DB_HOST') && EnvLoader::has('DB_USER') && EnvLoader::has('DB_NAME');

    if (!$has_mysql_vars && !$has_db_vars) {
        throw new \RuntimeException(
            "Missing required database variables. " .
            "Either set MYSQL_* (Railway) or DB_* (Local) environment variables."
        );
    }

    // Validate other required variables
    EnvLoader::requireAll([
        'APP_ENV',
        'APP_SECRET',
        'SMTP_HOST',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
    ]);
} catch (\RuntimeException $e) {
    // Development: Show the error
    if (EnvLoader::get('APP_ENV') === 'development') {
        echo "<h1>⚠️ Configuration Error</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Action:</strong> Copy <code>.env.example</code> to <code>.env.local</code> and fill in your values.</p>";
        exit(1);
    }

    // Production: Log silently and show generic error
    error_log('[USMS Bootstrap] ' . $e->getMessage());
    http_response_code(500);
    echo "Application configuration error. Please contact support.";
    exit(1);
}
