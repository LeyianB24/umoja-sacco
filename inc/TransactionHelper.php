<?php
/**
 * inc/TransactionHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\TransactionService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('TransactionHelper')) {
    class_alias(\USMS\Services\TransactionService::class, 'TransactionHelper');
}

// Ensure the static method 'record' works as before
if (!function_exists('record_legacy_transaction')) {
    function record_legacy_transaction($params) {
        return \USMS\Services\TransactionService::recordDoubleEntry($params);
    }
}
