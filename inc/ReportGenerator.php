<?php
/**
 * inc/ReportGenerator.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Reports\ReportService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('ReportGenerator')) {
    class_alias(\USMS\Reports\ReportService::class, 'ReportGenerator');
}
