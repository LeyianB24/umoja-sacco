<?php
/**
 * inc/RegistrationHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\RegistrationService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('RegistrationHelper')) {
    class_alias(\USMS\Services\RegistrationService::class, 'RegistrationHelper');
}
