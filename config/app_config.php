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
// 4. SOCIAL MEDIA & CONTACT LINKS
// ============================================================
define('SOCIAL_FACEBOOK', 'https://facebook.com/umojadriverssacco');
define('SOCIAL_TWITTER', 'https://twitter.com/umojadrivers');
define('SOCIAL_INSTAGRAM', 'https://instagram.com/umojadriverssacco');
define('SOCIAL_YOUTUBE', 'https://youtube.com/umojadriverssacco');
define('SOCIAL_TIKTOK', 'https://tiktok.com/@umojadriverssacco');
// WhatsApp uses COMPANY_PHONE logic in footers

// ============================================================
// 5. SECURITY
// ============================================================
define('APP_SECRET', 'a-very-long-random-secret-you-generate');
define('APP_ENV', 'sandbox'); // 'sandbox' or 'production' (Live-Simulation Mode)

// ============================================================
// 5. M-PESA CONFIGURATION (LEGACY REMOVED)
// ============================================================
// Configuration is now handled by GatewayFactory via config/environment.php

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
// 8. SUPPORT SYSTEM CONFIGURATION
// ============================================================
define('SUPPORT_ROUTING_MAP', [
    'loans'       => 'manager',         // Loan applications & repayments
    'savings'     => 'accountant',      // Savings & deposits
    'shares'      => 'superadmin',      // Shares & equity
    'welfare'     => 'welfare_officer', // Welfare & benefits
    'investments' => 'manager',         // Investment inquiries
    'technical'   => 'superadmin',      // Technical issues
    'profile'     => 'clerk',           // Account / profile updates
    'withdrawals' => 'accountant',      // Withdrawals & M-Pesa
    'general'     => 'superadmin'       // General inquiries
]);

// ============================================================
// 9. THEME COLORS (Optional - for PHP usage)
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
