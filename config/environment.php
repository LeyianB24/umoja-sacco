<?php
/**
 * Application Environment Configuration - Loads from .env.local
 * Supports sandbox and production environments
 * IMPORTANT: Actual credentials are in .env.local (git-ignored)
 */

require_once __DIR__ . '/EnvLoader.php';

use USMS\Config\EnvLoader;

// Load environment variables from .env.local
EnvLoader::load();

// APP_ENV is defined in app.php.
// If not defined (e.g. standalone script), check env or default to sandbox.
if (!defined('APP_ENV')) {
    define('APP_ENV', EnvLoader::get('APP_ENV', 'sandbox'));
}

$environments = [
    'sandbox' => [
        'mpesa' => [
            'base_url' => EnvLoader::get('MPESA_SANDBOX_BASE_URL', 'https://sandbox.safaricom.co.ke'),
            'consumer_key' => EnvLoader::get('MPESA_SANDBOX_CONSUMER_KEY', ''),
            'consumer_secret' => EnvLoader::get('MPESA_SANDBOX_CONSUMER_SECRET', ''),
            'shortcode' => EnvLoader::get('MPESA_SANDBOX_SHORTCODE', '174379'),
            'passkey' => EnvLoader::get('MPESA_SANDBOX_PASSKEY', ''),
            'callback_url' => EnvLoader::get('MPESA_SANDBOX_CALLBACK_URL', ''),
            'b2c_shortcode' => EnvLoader::get('MPESA_SANDBOX_B2C_SHORTCODE', '600981'),
            'b2c_initiator_name' => EnvLoader::get('MPESA_SANDBOX_B2C_INITIATOR_NAME', 'testapi'),
            'b2c_security_credential' => EnvLoader::get('MPESA_SANDBOX_B2C_SECURITY_CREDENTIAL', ''),
            'b2c_timeout_url' => EnvLoader::get('MPESA_SANDBOX_B2C_TIMEOUT_URL', ''),
            'b2c_result_url' => EnvLoader::get('MPESA_SANDBOX_B2C_RESULT_URL', ''),
        ],
        'email' => [
            'smtp_host' => EnvLoader::get('SMTP_HOST', 'smtp.gmail.com'),
            'smtp_username' => EnvLoader::get('SMTP_USERNAME', ''),
            'smtp_password' => EnvLoader::get('SMTP_PASSWORD', ''),
            'smtp_port' => EnvLoader::getInt('SMTP_PORT', 587),
            'from_email' => EnvLoader::get('SMTP_USERNAME', ''),
            'from_name' => EnvLoader::get('SMTP_FROM_NAME', 'Umoja Drivers Sacco'),
        ],
        'paystack' => [
            'secret_key' => EnvLoader::get('PAYSTACK_TEST_SECRET_KEY', ''),
            'public_key' => EnvLoader::get('PAYSTACK_TEST_PUBLIC_KEY', ''),
        ]
    ],
    'production' => [
        'mpesa' => [
            'base_url' => EnvLoader::get('MPESA_LIVE_BASE_URL', 'https://api.safaricom.co.ke'),
            'consumer_key' => EnvLoader::get('MPESA_LIVE_CONSUMER_KEY', ''),
            'consumer_secret' => EnvLoader::get('MPESA_LIVE_CONSUMER_SECRET', ''),
            'shortcode' => EnvLoader::get('MPESA_LIVE_SHORTCODE', ''),
            'passkey' => EnvLoader::get('MPESA_LIVE_PASSKEY', ''),
            'callback_url' => EnvLoader::get('MPESA_LIVE_CALLBACK_URL', ''),
            'b2c_shortcode' => EnvLoader::get('MPESA_LIVE_B2C_SHORTCODE', ''),
            'b2c_initiator_name' => EnvLoader::get('MPESA_LIVE_B2C_INITIATOR_NAME', ''),
            'b2c_security_credential' => EnvLoader::get('MPESA_LIVE_B2C_SECURITY_CREDENTIAL', ''),
            'b2c_timeout_url' => EnvLoader::get('MPESA_LIVE_B2C_TIMEOUT_URL', ''),
            'b2c_result_url' => EnvLoader::get('MPESA_LIVE_B2C_RESULT_URL', ''),
        ],
        'email' => [
            'smtp_host' => EnvLoader::get('SMTP_HOST', 'smtp.gmail.com'),
            'smtp_username' => EnvLoader::get('SMTP_USERNAME', ''),
            'smtp_password' => EnvLoader::get('SMTP_PASSWORD', ''),
            'smtp_port' => EnvLoader::getInt('SMTP_PORT', 587),
            'from_email' => EnvLoader::get('SMTP_USERNAME', ''),
            'from_name' => EnvLoader::get('SMTP_FROM_NAME', 'Umoja Drivers Sacco'),
        ],
        'paystack' => [
            'secret_key' => EnvLoader::get('PAYSTACK_LIVE_SECRET_KEY', ''),
            'public_key' => EnvLoader::get('PAYSTACK_LIVE_PUBLIC_KEY', ''),
        ]
    ]
];

// Current environment config - Fallback to sandbox if key doesn't exist
$current_env = array_key_exists(APP_ENV, $environments) ? APP_ENV : 'sandbox';
$config = $environments[$current_env];

// Define constants for global use
if (!defined('MPESA_BASE_URL')) define('MPESA_BASE_URL', $config['mpesa']['base_url']);
if (!defined('MPESA_SHORTCODE')) define('MPESA_SHORTCODE', $config['mpesa']['shortcode']);
if (!defined('MPESA_PASSKEY')) define('MPESA_PASSKEY', $config['mpesa']['passkey']);
if (!defined('MPESA_CALLBACK_URL')) define('MPESA_CALLBACK_URL', $config['mpesa']['callback_url']);

return $config;
