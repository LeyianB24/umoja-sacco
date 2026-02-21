<?php
declare(strict_types=1);
/**
 * core/Database/Connection.php
 * USMS\Database\Connection — Managed MySQLi singleton.
 *
 * Provides a single, reusable database connection across the application.
 * Wraps the same MySQLi driver (unchanged) behind a clean interface,
 * so existing code (FinancialEngine, HRService, etc.) that receives $conn
 * continues to work without any rewrites.
 *
 * Usage:
 *   $conn = Connection::getInstance()->getConnection();
 *
 * Or use the global alias helper:
 *   $conn = db();
 */

namespace USMS\Database;

use mysqli;
use RuntimeException;

class Connection
{
    private static ?self $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        // Configuration — reads from app config constants if available,
        // falls back to XAMPP defaults for local development.
        $host   = defined('DB_HOST')   ? DB_HOST   : 'localhost';
        $user   = defined('DB_USER')   ? DB_USER   : 'root';
        $pass   = defined('DB_PASS')   ? DB_PASS   : '';
        $dbname = defined('DB_NAME')   ? DB_NAME   : 'umoja_drivers_sacco';
        $port   = defined('DB_PORT')   ? (int)DB_PORT : 3306;

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->connection = new mysqli($host, $user, $pass, $dbname, $port);
            $this->connection->set_charset('utf8mb4');
        } catch (\mysqli_sql_exception $e) {
            $msg = (defined('APP_ENV') && APP_ENV === 'development')
                ? 'Database connection failed: ' . $e->getMessage()
                : 'System error. Please try again later.';
            throw new RuntimeException($msg, (int)$e->getCode(), $e);
        }
    }

    /** Get the singleton instance. */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Get the raw MySQLi connection (fully compatible with existing code). */
    public function getConnection(): mysqli
    {
        // Ping and reconnect if connection was dropped (long-running scripts)
        if (!$this->connection->ping()) {
            self::$instance = null;
            return self::getInstance()->getConnection();
        }
        return $this->connection;
    }

    /** Convenience: begin a transaction. */
    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    /** Convenience: commit the active transaction. */
    public function commit(): void
    {
        $this->connection->commit();
    }

    /** Convenience: rollback the active transaction. */
    public function rollback(): void
    {
        $this->connection->rollback();
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize a singleton.');
    }
}

/**
 * Global convenience helper — returns the raw mysqli connection.
 * Drop-in replacement for the global $conn.
 *
 *   // Instead of: global $conn;
 *   // Use:        $conn = db();
 */
if (!function_exists('db')) {
    function db(): mysqli
    {
        return Connection::getInstance()->getConnection();
    }
}
