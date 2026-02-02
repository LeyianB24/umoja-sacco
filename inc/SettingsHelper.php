<?php
// usms/inc/SettingsHelper.php

class SettingsHelper {
    private static $cache = [];
    private static $db = null;

    /**
     * Initialize connection if not passed
     */
    private static function init() {
        if (self::$db === null) {
            require_once __DIR__ . '/../config/db_connect.php';
            global $conn;
            self::$db = $conn;
        }
    }

    /**
     * Fetch a setting value by key
     */
    public static function get($key, $default = null) {
        self::init();

        // Return from cache if already fetched
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $stmt = self::$db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            self::$cache[$key] = $row['setting_value'];
            return $row['setting_value'];
        }

        return $default;
    }

    /**
     * Update a setting value
     */
    public static function set($key, $value, $admin_id = null) {
        self::init();

        $stmt = self::$db->prepare("INSERT INTO system_settings (setting_key, setting_value, last_updated_by) 
                                    VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), last_updated_by = VALUES(last_updated_by)");
        $stmt->bind_param("ssi", $key, $value, $admin_id);
        
        $success = $stmt->execute();
        if ($success) {
            self::$cache[$key] = $value;
        }
        return $success;
    }

    /**
     * Fetch all settings as an associative array
     */
    public static function all() {
        self::init();
        $result = self::$db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        self::$cache = array_merge(self::$cache, $settings);
        return $settings;
    }
}
