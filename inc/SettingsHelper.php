<?php
/**
 * inc/SettingsHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\SettingsService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('SettingsHelper')) {
    class_alias(\USMS\Services\SettingsService::class, 'SettingsHelper');
}
