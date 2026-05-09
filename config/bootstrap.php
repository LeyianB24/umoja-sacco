<?php
/**
 * config/bootstrap.php
 * Application bootstrap and environment validation
 * Call this FIRST in your application entry point
 */

require_once __DIR__ . '/EnvLoader.php';

use USMS\Config\EnvLoader;

// Load environment variables
EnvLoader::load();

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
