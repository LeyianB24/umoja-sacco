<?php
/**
 * inc/SystemUserService.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\SystemUserService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('SystemUserService')) {
    class_alias(\USMS\Services\SystemUserService::class, 'SystemUserService');
}
