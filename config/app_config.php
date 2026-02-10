<?php
// config/app_config.php
// MASTER Configuration - Single Source of Truth

// ============================================================
// 1. PATH CONFIGURATION (Required First)
// ============================================================
define('BASE_URL', '/usms');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
// Remove any trailing slash from host and leading slash from BASE_URL for clean concat if needed, 
// but BASE_URL here is /usms, so $protocol://$host$BASE_URL is correct.
define('SITE_URL', $protocol . "://" . rtrim($host, '/') . '/' . ltrim(BASE_URL, '/'));
define('ASSET_BASE', BASE_URL . '/public/assets');

// Load Composer Autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ============================================================
// 2. COMPANY INFORMATION
// ============================================================
define('SITE_NAME', 'Umoja Drivers Sacco');
define('SITE_SHORT_NAME', 'UDS');
define('SITE_TAGLINE', 'Together We Grow');

// Contact Details
define('COMPANY_EMAIL', 'info@umojadrivers.co.ke');
define('COMPANY_PHONE', '+254 755 758 208');
define('COMPANY_ADDRESS', 'CBD, Nairobi, Kenya');
define('COMPANY_OFFICE', 'Umoja Sacco Plaza, 4th Floor');

// Legacy aliases (for backward compatibility - will remove later)
define('OFFICE_EMAIL', COMPANY_EMAIL);
define('OFFICE_PHONE', COMPANY_PHONE);
define('OFFICE_LOCATION', COMPANY_OFFICE . ', ' . COMPANY_ADDRESS);
define('TAGLINE', SITE_TAGLINE);

// ============================================================
// 3. BRANDING & ASSETS
// ============================================================
define('SITE_LOGO', ASSET_BASE . '/images/people_logo.png');
define('SITE_FAVICON', ASSET_BASE . '/images/people_logo.png');
define('BACKGROUND_IMAGE', ASSET_BASE . '/images/sacco4.jpg');

// ============================================================
// 4. SECURITY
// ============================================================
define('APP_SECRET', 'a-very-long-random-secret-you-generate');
define('APP_ENV', 'development'); // 'development' or 'production'

// ============================================================
// 5. M-PESA CONFIGURATION
// ============================================================
// Load M-Pesa credentials from separate config file
$mpesa_config = require __DIR__ . '/mpesa_config.php';

// Make M-Pesa settings available as constants
define('MPESA_ENVIRONMENT', $mpesa_config['environment']);
define('MPESA_CONSUMER_KEY', $mpesa_config['consumer_key']);
define('MPESA_CONSUMER_SECRET', $mpesa_config['consumer_secret']);
define('MPESA_SHORTCODE', $mpesa_config['shortcode']);
define('MPESA_PASSKEY', $mpesa_config['passkey']);
define('MPESA_B2C_SHORTCODE', $mpesa_config['b2c_shortcode']);
define('MPESA_B2C_INITIATOR', $mpesa_config['b2c_initiator_name']);
define('MPESA_B2C_CREDENTIAL', $mpesa_config['b2c_security_credential']);
define('MPESA_SANDBOX_URL', $mpesa_config['sandbox_url']);
define('MPESA_LIVE_URL', $mpesa_config['live_url']);
define('MPESA_CALLBACK_URL', $mpesa_config['callback_url']);
define('MPESA_B2C_RESULT_URL', $mpesa_config['b2c_result_url']);
define('MPESA_B2C_TIMEOUT_URL', $mpesa_config['b2c_timeout_url']);

// ============================================================
// 6. NOTIFICATION SETTINGS
// ============================================================
// Email
define('EMAIL_ENABLED', true);
define('EMAIL_FROM', COMPANY_EMAIL);
define('EMAIL_FROM_NAME', SITE_NAME);

// SMS
define('SMS_ENABLED', true);
define('SMS_SENDER_ID', 'UMOJA_SACCO');
define('SMS_API_KEY', 'atsk_aac0d19755a64e3664f9bcb4653fa983e3e94fc90acdff7bca92c1b859e4f4c6aede328c'); // Set your SMS API key here (e.g., Africa's Talking)

// Notification Triggers
define('NOTIFY_ON_LOAN_APPROVAL', true);
define('NOTIFY_ON_WITHDRAWAL', true);
define('NOTIFY_ON_DEPOSIT', true);
define('NOTIFY_ON_WELFARE_GRANT', true);

// ============================================================
// 7. EXPORT SETTINGS
// ============================================================
define('EXPORT_ENABLED', true);
define('PDF_ORIENTATION', 'portrait');
define('EXCEL_DEFAULT_FORMAT', 'xlsx');

// ============================================================
// 8. THEME COLORS (Optional - for PHP usage)
// ============================================================
$theme = [
    'primary'       => '#1b5e20', // Sacco Green
    'primary_dark'  => '#0b3d02',
    'accent'        => '#f4c430', // Sacco Gold
    'muted'         => '#6b7280'
];

// ============================================================
// 9. ERROR REPORTING (Based on Environment)
// ============================================================
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
