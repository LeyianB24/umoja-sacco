<?php
/**
 * inc/CronHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\CronService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('CronHelper')) {
    class_alias(\USMS\Services\CronService::class, 'CronHelper');
}
