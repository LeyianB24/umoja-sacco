<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/MpesaGateway.php';
require_once __DIR__ . '/PaystackGateway.php';

class GatewayFactory {
    /**
     * Get a gateway instance
     * 
     * @param string $provider 'mpesa' or 'paystack'
     * @return PaymentGatewayInterface
     */
    public static function get($provider = 'mpesa') {
        $env_config = require __DIR__ . '/../config/environment.php';
        $current_env = defined('APP_ENV') ? APP_ENV : 'sandbox';
        
        if ($provider === 'mpesa') {
            return new MpesaGateway($env_config['mpesa'], $current_env);
        } elseif ($provider === 'paystack') {
            return new PaystackGateway($env_config['paystack'], $current_env);
        }
        
        throw new Exception("Unsupported Payment Provider: $provider");
    }
}
