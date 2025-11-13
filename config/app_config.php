<?php
// usms/config/app_config.php
// Global configuration for Umoja Sacco Management System

// ----------------------------
// SITE CONFIG
// ----------------------------
define('SITE_NAME', 'Umoja Drivers Sacco');
define('TAGLINE', 'Together We Grow');

// ----------------------------
// PATH CONFIG
// ----------------------------
// The system is running from  http://localhost/usms/
// So BASE_URL should NOT include /public
define('BASE_URL', '/usms');
define('ASSET_BASE', BASE_URL . '/public/assets');


// Security secret (used for password reset tokens, sessions, etc)
define('APP_SECRET', 'a-very-long-random-secret-you-generate');

// ----------------------------
// THEME COLORS
// ----------------------------
$theme = [
    'primary'       => '#1b5e20', // Sacco Green
    'primary_dark'  => '#0b3d02',
    'accent'        => '#f4c430', // Sacco Gold
    'muted'         => '#6b7280'
];

// ----------------------------
// ENVIRONMENT & DEBUG
// ----------------------------
define('APP_ENV', 'development'); // change to 'production' later

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    // Development mode: show all errors
}
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    // Production mode: hide errors
}
