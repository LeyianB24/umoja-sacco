<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use PDO;

/**
 * USMS\Services\SettingsService
 * Centralized System Settings Manager with caching.
 */
class SettingsService {
    private static array $cache = [];
    private PDO $db;

    private static ?SettingsService $instance = null;

    private static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Static gateway for convenience
     */
    public static function get(string $key, $default = null) {
        return self::getInstance()->fetch($key, $default);
    }

    private function fetch(string $key, $default = null) {
        // Return from cache if already fetched
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            self::$cache[$key] = $row['setting_value'];
            return $row['setting_value'];
        }

        return $default;
    }

    /**
     * Update a setting value
     */
    public function set(string $key, string $value, ?int $admin_id = null): bool {
        $sql = "INSERT INTO system_settings (setting_key, setting_value, last_updated_by) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), last_updated_by = VALUES(last_updated_by)";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$key, $value, $admin_id]);
        
        if ($success) {
            self::$cache[$key] = $value;
        }
        return $success;
    }

    /**
     * Fetch all settings as an associative array
     */
    public function all(): array {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        self::$cache = array_merge(self::$cache, $settings);
        return $settings;
    }

    public static function staticSet(string $key, string $value, ?int $admin_id = null): bool {
        return self::getInstance()->set($key, $value, $admin_id);
    }

    public static function staticAll(): array {
        return self::getInstance()->all();
    }

    /**
     * @deprecated Use SettingsService::get()
     */
    public static function quickGet(string $key, $default = null) {
        return self::get($key, $default);
    }
}
