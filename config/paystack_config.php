<?php
// config/paystack_config.php
// Paystack Configuration for Withdrawals

return [
    'environment' => 'test', // 'test' or 'live'
    
    // Test Keys (Working sandbox keys)
    'public_key' => 'pk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6', // Add public key if needed
    'secret_key' => 'sk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6',
    
    // Live Keys  
    'live_public_key' => '',
    'live_secret_key' => '',
    
    // API Endpoints
    'base_url' => 'https://api.paystack.co',
    
    // Transfer Settings
    'transfer_source' => 'balance',
    'currency' => 'KES', // Kenya Shillings
    
    // Webhook URLs
    'webhook_url' => BASE_URL . '/public/api/paystack_webhook.php',
];
?>
