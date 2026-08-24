<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/MpesaGateway.php';
require_once __DIR__ . '/PaystackGateway.php';
use USMS\Services\Gateways\PaymentGatewayInterface;

class GatewayFactory {
    /**
     * Get a gateway instance
     * 
     * @param string $provider 'mpesa' or 'paystack'
     * @return PaymentGatewayInterface
     */
    public static function get($provider = 'mpesa'): PaymentGatewayInterface {
        $env_config = require __DIR__ . '/../config/environment.php';
        $mpesa_env = defined('MPESA_ENV') ? MPESA_ENV : (defined('APP_ENV') ? APP_ENV : 'sandbox');
        $paystack_env = defined('PAYSTACK_ENV') ? PAYSTACK_ENV : (defined('APP_ENV') ? APP_ENV : 'sandbox');
        
        if ($provider === 'mpesa') {
            return new \USMS\Services\Gateways\MpesaService($env_config['mpesa'], $mpesa_env);
        } elseif ($provider === 'paystack') {
            return new \USMS\Services\Gateways\PaystackService($env_config['paystack'], $paystack_env);
        }
        
        throw new Exception("Unsupported Payment Provider: $provider");
    }
}
