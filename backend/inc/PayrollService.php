<?php
/**
 * inc/PayrollService.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\PayrollService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('PayrollService')) {
    class_alias(\USMS\Services\PayrollService::class, 'PayrollService');
}
