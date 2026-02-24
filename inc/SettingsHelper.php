<?php
/**
 * inc/SettingsHelper.php (LEGACY BRIDGE)
 * Redirects to the namespaced USMS\Services\SettingsService class.
 * Handles legacy static calls.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('SettingsHelper')) {
    class SettingsHelper {
        public static function get($key, $default = null) {
            return \USMS\Services\SettingsService::get($key, $default);
        }

        public static function set($key, $value, $admin_id = null) {
            return \USMS\Services\SettingsService::staticSet($key, $value, $admin_id);
        }

        public static function all() {
            return \USMS\Services\SettingsService::staticAll();
        }
    }
}
