<?php
/**
 * Application Environment Configuration
 * Supports sandbox and production environments
 */

// Detect environment from system variable or default to sandbox
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'sandbox');
}

$environments = [
    'sandbox' => [
        'mpesa' => [
            'base_url' => 'https://sandbox.safaricom.co.ke',
            'consumer_key' => 'SANDBOX_KEY',
            'consumer_secret' => 'SANDBOX_SECRET',
            'shortcode' => '174379',
            'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
            'callback_url' => 'https://yourdomain.com/usms/public/member/mpesa_callback.php'
        ],
        'email' => [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_username' => 'leyianbeza24@gmail.com',
            'smtp_password' => 'duzb mbqt fnsz ipkg',
            'smtp_port' => 587,
            'from_email' => 'leyianbeza24@gmail.com',
            'from_name' => 'Umoja Drivers Sacco'
        ],
        'paystack' => [
            'secret_key' => 'sk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6',
            'public_key' => 'pk_test_a03e1eacf9f8e97fcc25c4ae2556983ffc9b2fe6'
        ]
    ],
    'production' => [
        'mpesa' => [
            'base_url' => 'https://api.safaricom.co.ke',
            'consumer_key' => 'LIVE_KEY', // Replace with real live keys
            'consumer_secret' => 'LIVE_SECRET',
            'shortcode' => 'LIVE_SHORTCODE',
            'passkey' => 'LIVE_PASSKEY',
            'callback_url' => 'https://live.umojasacco.co.ke/public/member/mpesa_callback.php'
        ],
        'email' => [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_username' => 'leyianbeza24@gmail.com',
            'smtp_password' => 'duzb mbqt fnsz ipkg',
            'smtp_port' => 587,
            'from_email' => 'info@umojasacco.co.ke',
            'from_name' => 'Umoja Drivers Sacco'
        ],
        'paystack' => [
            'secret_key' => 'sk_live_REPLACE_THIS',
            'public_key' => 'pk_live_REPLACE_THIS'
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
