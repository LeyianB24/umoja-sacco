<?php
/**
 * inc/EmployeeService.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\EmployeeService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('EmployeeService')) {
    class_alias(\USMS\Services\EmployeeService::class, 'EmployeeService');
}
