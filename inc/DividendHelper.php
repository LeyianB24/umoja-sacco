<?php
/**
 * inc/DividendHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\DividendService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('DividendHelper')) {
    class_alias(\USMS\Services\DividendService::class, 'DividendHelper');
}
