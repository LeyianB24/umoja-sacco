<?php
/**
 * inc/FinancialEngine.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\FinancialService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('FinancialEngine')) {
    class_alias(\USMS\Services\FinancialService::class, 'FinancialEngine');
}
