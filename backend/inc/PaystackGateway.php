<?php
/**
 * inc/PaystackGateway.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\Gateways\PaystackService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('PaystackGateway')) {
    class_alias(\USMS\Services\Gateways\PaystackService::class, 'PaystackGateway');
}
