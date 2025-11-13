<?php
// usms/config/mpesa_config.php

return [
    'environment' => 'sandbox',

    // Daraja Sandbox Credentials
    'consumer_key' => 'ZXkv0zLHjiDIGlg7cgKetgnwMiB4RYBzZa8rafaUxJwDQViN',
    'consumer_secret' => '6QK059IyYwFy772iGKkfjgi0FsHqPAMwT3YmD0ox9tTkVZAtMrInHRDDEwLzey1C',

    // Lipa na M-Pesa Credentials
    'shortcode' => '174379',
    'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    // URLs
    'sandbox_url' => 'https://sandbox.safaricom.co.ke',
    'live_url' => 'https://api.safaricom.co.ke',

    // Your public callback URL
    'callback_url' => 'https://abcdef.ngrok.io/usms/public/member/mpesa_callback.php'
];
?>
