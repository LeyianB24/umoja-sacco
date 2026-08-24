<?php
/**
 * inc/MpesaGateway.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\Gateways\MpesaService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('MpesaGateway')) {
    class_alias(\USMS\Services\Gateways\MpesaService::class, 'MpesaGateway');
}
