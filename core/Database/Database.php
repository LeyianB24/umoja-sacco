<?php
declare(strict_types=1);

namespace USMS\Database;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        global $db_config;

        if (!isset($db_config)) {
            // Fallback for safety if config not loaded
            $db_config = [
                'host'     => 'localhost',
                'user'     => 'root',
                'pass'     => '',
                'dbname'   => 'umoja_drivers_sacco',
                'charset'  => 'utf8mb4'
            ];
        }

        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $user = $db_config['user'];
        $pass = $db_config['pass'];
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("[USMS] PDO connection failed: " . $e->getMessage());
            if (defined('APP_ENV') && APP_ENV === 'development') {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            } else {
                \USMS\Http\ErrorHandler::abort(500, "Critical: Database connection failed.");
            }
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    /**
     * Shorthand for simple queries
     */
    public function query(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row
     */
    public function fetch(string $sql, array $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
}
