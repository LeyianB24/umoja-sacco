<?php
/**
 * inc/LoanHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\LoanService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('LoanHelper')) {
    class_alias(\USMS\Services\LoanService::class, 'LoanHelper');
}
