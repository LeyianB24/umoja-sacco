<?php
/**
 * config/app.php
 * MASTER Configuration - Single Source of Truth for USMS
 * Consolidates app_config.php and db_connect.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. BASE PATHS
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
if (!defined('BASE_URL'))  define('BASE_URL', '/usms');

// 2. AUTOLOAD
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// 3. ERROR HANDLING
\USMS\Http\ErrorHandler::register();

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
if (!defined('SITE_URL'))   define('SITE_URL', $protocol . '://' . rtrim($host, '/') . '/' . ltrim(BASE_URL, '/'));
if (!defined('ASSET_BASE')) define('ASSET_BASE', BASE_URL . '/public/assets');


// 4. CORE IDENTITY
if (!defined('SITE_NAME'))       define('SITE_NAME',       'Umoja Drivers Sacco');
if (!defined('SITE_SHORT_NAME')) define('SITE_SHORT_NAME', 'UDS');
if (!defined('SITE_TAGLINE'))    define('SITE_TAGLINE',    'Together We Grow');
if (!defined('COMPANY_EMAIL'))   define('COMPANY_EMAIL',   'info@umojadrivers.co.ke');
if (!defined('COMPANY_PHONE'))   define('COMPANY_PHONE',   '+254 755 758 208');
if (!defined('COMPANY_ADDRESS')) define('COMPANY_ADDRESS', 'CBD, Nairobi, Kenya');
if (!defined('COMPANY_OFFICE'))  define('COMPANY_OFFICE',  'Umoja Sacco Plaza, 4th Floor');

// Legacy Aliases
if (!defined('OFFICE_EMAIL'))    define('OFFICE_EMAIL',    COMPANY_EMAIL);
if (!defined('OFFICE_PHONE'))    define('OFFICE_PHONE',    COMPANY_PHONE);
if (!defined('OFFICE_LOCATION')) define('OFFICE_LOCATION', COMPANY_OFFICE . ', ' . COMPANY_ADDRESS);
if (!defined('TAGLINE'))         define('TAGLINE',         SITE_TAGLINE);

// 4.1 BRANDING & ASSETS
if (!defined('SITE_LOGO'))        define('SITE_LOGO',        ASSET_BASE . '/images/people_logo.png');
if (!defined('SITE_FAVICON'))     define('SITE_FAVICON',     ASSET_BASE . '/images/people_logo.png');
if (!defined('BACKGROUND_IMAGE')) define('BACKGROUND_IMAGE', ASSET_BASE . '/images/sacco12.jpg');

// 4.2 SUPPORT ROUTING
if (!defined('SUPPORT_ROUTING_MAP')) {
    define('SUPPORT_ROUTING_MAP', [
        'loans'       => 'manager',
        'savings'     => 'accountant',
        'shares'      => 'superadmin',
        'welfare'     => 'welfare_officer',
        'investments' => 'manager',
        'technical'   => 'superadmin',
        'profile'     => 'clerk',
        'withdrawals' => 'accountant',
        'general'     => 'superadmin',
    ]);
}

// 4.3 SOCIAL MEDIA
if (!defined('SOCIAL_FACEBOOK'))  define('SOCIAL_FACEBOOK',  'https://facebook.com/umojadriverssacco');
if (!defined('SOCIAL_TWITTER'))   define('SOCIAL_TWITTER',   'https://twitter.com/umojadrivers');
if (!defined('SOCIAL_INSTAGRAM')) define('SOCIAL_INSTAGRAM', 'https://instagram.com/umojadriverssacco');
if (!defined('SOCIAL_YOUTUBE'))   define('SOCIAL_YOUTUBE',   'https://youtube.com/umojadriverssacco');
if (!defined('SOCIAL_TIKTOK'))    define('SOCIAL_TIKTOK',    'https://tiktok.com/@umojadriverssacco');

// 5. ENVIRONMENT & SECURITY
if (!defined('APP_ENV'))    define('APP_ENV',    getenv('APP_ENV') ?: 'development');
if (!defined('APP_SECRET')) define('APP_SECRET', 'a-very-long-random-secret-you-generate');

// 6. DATABASE CONFIGURATION (Legacy mysqli support)
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
// Global $db alias for legacy pages
$db = $conn;

// 7. ENVIRONMENT SPECIFIC SETTINGS (M-Pesa, Email, etc.)
$config = require_once __DIR__ . '/environment.php';

// 8. NOTIFICATION SETTINGS
if (!defined('EMAIL_ENABLED'))   define('EMAIL_ENABLED',   true);
if (!defined('EMAIL_FROM'))      define('EMAIL_FROM',      COMPANY_EMAIL);
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', SITE_NAME);

if (!defined('SMS_ENABLED'))     define('SMS_ENABLED',     true);
if (!defined('SMS_SENDER_ID'))   define('SMS_SENDER_ID',   'UMOJA_SACCO');
if (!defined('SMS_API_KEY'))     define('SMS_API_KEY',     'atsk_aac0d19755a64e3664f9bcb4653fa983e3e94fc90acdff7bca92c1b859e4f4c6aede328c');

if (!defined('SMTP_HOST'))       define('SMTP_HOST',       $config['email']['smtp_host'] ?? 'smtp.gmail.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT',       $config['email']['smtp_port'] ?? 587);
if (!defined('SMTP_USERNAME'))   define('SMTP_USERNAME',   $config['email']['smtp_username'] ?? 'leyianbeza24@gmail.com');
if (!defined('SMTP_PASSWORD'))   define('SMTP_PASSWORD',   $config['email']['smtp_password'] ?? 'duzb mbqt fnsz ipkg');
if (!defined('SMTP_SECURE'))     define('SMTP_SECURE',     'tls');
if (!defined('SMTP_FROM_NAME'))  define('SMTP_FROM_NAME',  SITE_NAME);

// Notification Triggers
if (!defined('NOTIFY_ON_LOAN_APPROVAL')) define('NOTIFY_ON_LOAN_APPROVAL', true);
if (!defined('NOTIFY_ON_WITHDRAWAL'))    define('NOTIFY_ON_WITHDRAWAL',    true);
if (!defined('NOTIFY_ON_DEPOSIT'))       define('NOTIFY_ON_DEPOSIT',       true);
if (!defined('NOTIFY_ON_WELFARE_GRANT')) define('NOTIFY_ON_WELFARE_GRANT', true);

// 9. EXPORT SETTINGS
if (!defined('EXPORT_ENABLED'))       define('EXPORT_ENABLED',       true);
if (!defined('PDF_ORIENTATION'))      define('PDF_ORIENTATION',      'portrait');
if (!defined('EXCEL_DEFAULT_FORMAT')) define('EXCEL_DEFAULT_FORMAT', 'xlsx');

// 10. THEME COLORS
$theme = [
    'primary'      => '#1b5e20',
    'primary_dark' => '#0b3d02',
    'accent'       => '#f4c430',
    'muted'        => '#6b7280',
];

// 11. ERROR REPORTING
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
