<?php
/**
 * inc/PaymentGatewayInterface.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\Gateways\PaymentGatewayInterface interface.
 */
require_once __DIR__ . '/../config/app.php';

// Interfaces cannot be aliased with class_alias in some versions of PHP if strict,
// but for backward compatibility, we can define it if not exists.
// However, class_alias works for interfaces too.
if (!interface_exists('PaymentGatewayInterface')) {
    class_alias(\USMS\Services\Gateways\PaymentGatewayInterface::class, 'PaymentGatewayInterface');
}
