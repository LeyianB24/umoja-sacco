<?php
/**
 * inc/StatementHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\StatementService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('StatementHelper')) {
    class_alias(\USMS\Services\StatementService::class, 'StatementHelper');
}
