<?php
/**
 * inc/HRService.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\HRService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('HRService')) {
    class_alias(\USMS\Services\HRService::class, 'HRService');
}
