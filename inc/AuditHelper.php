<?php
/**
 * inc/AuditHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\AuditService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('AuditHelper')) {
    class_alias(\USMS\Services\AuditService::class, 'AuditHelper');
}
