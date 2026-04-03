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
 */
try {
    EnvLoader::requireAll([
        'APP_ENV',
        'APP_SECRET',
        'DB_HOST',
        'DB_USER',
        'DB_NAME',
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
