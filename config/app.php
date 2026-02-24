<?php
/**
 * config/app.php
 * MASTER Configuration - Single Source of Truth for USMS
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. BASE PATHS
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/usms');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
define('SITE_URL', $protocol . '://' . rtrim($host, '/') . '/' . ltrim(BASE_URL, '/'));
define('ASSET_BASE', BASE_URL . '/public/assets');

// 2. AUTOLOAD
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// 3. CORE IDENTITY
define('SITE_NAME',       'Umoja Drivers Sacco');
define('SITE_SHORT_NAME', 'UDS');
define('SITE_TAGLINE',    'Together We Grow');
define('COMPANY_EMAIL',   'info@umojadrivers.co.ke');
define('COMPANY_PHONE',   '+254 755 758 208');
define('COMPANY_ADDRESS', 'CBD, Nairobi, Kenya');
define('COMPANY_OFFICE',  'Umoja Sacco Plaza, 4th Floor');

// 4. ENVIRONMENT & SECURITY
define('APP_ENV',    getenv('APP_ENV') ?: 'development'); // development, sandbox, production
define('APP_SECRET', 'a-very-long-random-secret-you-generate');

// 5. DATABASE CONFIGURATION (Legacy mysqli support)
$db_config = [
    'host'     => 'localhost',
    'user'     => 'root',
    'pass'     => '',
    'dbname'   => 'umoja_drivers_sacco',
    'charset'  => 'utf8mb4'
];

$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['dbname']);
if ($conn->connect_error) {
    error_log("[USMS] MySQL connection failed: " . $conn->connect_error);
    \USMS\Http\ErrorHandler::abort(500,
        APP_ENV === 'development'
            ? "Database connection failed: " . $conn->connect_error
            : "Error connecting to system database. Please try again later."
    );
}
$conn->set_charset($db_config['charset']);

// 6. ENVIRONMENT SPECIFIC SETTINGS (M-Pesa, Email, etc.)
require_once __DIR__ . '/environment.php';

// 7. NOTIFICATION SETTINGS
define('EMAIL_ENABLED',   true);
define('SMS_ENABLED',     true);
define('SMTP_HOST',       $config['email']['smtp_host'] ?? 'smtp.gmail.com');
define('SMTP_PORT',       $config['email']['smtp_port'] ?? 587);
define('SMTP_USERNAME',   $config['email']['smtp_username'] ?? 'leyianbeza24@gmail.com');
define('SMTP_PASSWORD',   $config['email']['smtp_password'] ?? 'duzb mbqt fnsz ipkg');

// 8. THEME COLORS
$theme = [
    'primary'      => '#1b5e20',
    'primary_dark' => '#0b3d02',
    'accent'       => '#f4c430',
    'muted'        => '#6b7280',
];

// 9. ERROR REPORTING
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
