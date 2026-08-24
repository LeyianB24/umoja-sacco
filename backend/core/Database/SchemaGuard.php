<?php
declare(strict_types=1);

namespace USMS\Database;

use mysqli;
use PDO;
use Throwable;

class SchemaGuard
{
    public static function ensureShareTransactions(mysqli $conn): void
    {
        self::ensureMysqliTable($conn, 'share_transactions', self::shareTransactionsSql());
    }

    public static function ensureShareTransactionsPdo(PDO $pdo): void
    {
        self::ensurePdoTable($pdo, 'share_transactions', self::shareTransactionsSql());
    }

    public static function ensureVehicleIncome(mysqli $conn): void
    {
        self::ensureVehicles($conn);
        self::ensureVehicleExpenses($conn);
        self::ensureMysqliTable($conn, 'vehicle_income', self::vehicleIncomeSql());
    }

    private static function ensureVehicles(mysqli $conn): void
    {
        self::ensureMysqliTable($conn, 'vehicles', self::vehiclesSql());
    }

    private static function ensureVehicleExpenses(mysqli $conn): void
    {
        self::ensureMysqliTable($conn, 'vehicle_expenses', self::vehicleExpensesSql());
    }

    private static function ensureMysqliTable(mysqli $conn, string $table, string $createSql): void
    {
        try {
            $conn->query("SHOW CREATE TABLE `{$table}`");
            return;
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), "doesn't exist in engine") !== false) {
                $conn->query("DROP TABLE IF EXISTS `{$table}`");
            }
        }

        $conn->query($createSql);
    }

    private static function ensurePdoTable(PDO $pdo, string $table, string $createSql): void
    {
        try {
            $pdo->query("SHOW CREATE TABLE `{$table}`");
            return;
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), "doesn't exist in engine") !== false) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }
        }

        $pdo->exec($createSql);
    }

    private static function shareTransactionsSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS `share_transactions` (
                `share_transaction_id` INT NOT NULL AUTO_INCREMENT,
                `member_id` INT NOT NULL,
                `units` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `total_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `transaction_type` VARCHAR(32) NOT NULL DEFAULT 'purchase',
                `reference_no` VARCHAR(100) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`share_transaction_id`),
                KEY `idx_share_transactions_member` (`member_id`),
                KEY `idx_share_transactions_created` (`created_at`),
                KEY `idx_share_transactions_type` (`transaction_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
    }

    private static function vehicleIncomeSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS `vehicle_income` (
                `vehicle_income_id` INT NOT NULL AUTO_INCREMENT,
                `vehicle_id` INT NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `income_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `description` VARCHAR(255) DEFAULT NULL,
                `recorded_by` INT DEFAULT NULL,
                PRIMARY KEY (`vehicle_income_id`),
                KEY `idx_vehicle_income_vehicle` (`vehicle_id`),
                KEY `idx_vehicle_income_date` (`income_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
    }

    private static function vehiclesSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS `vehicles` (
                `vehicle_id` INT NOT NULL AUTO_INCREMENT,
                `reg_no` VARCHAR(50) NOT NULL,
                `model` VARCHAR(100) DEFAULT NULL,
                `year` YEAR DEFAULT NULL,
                `status` VARCHAR(32) DEFAULT 'active',
                `investment_id` INT DEFAULT NULL,
                `purchase_cost` DECIMAL(12,2) DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `assigned_route` VARCHAR(150) DEFAULT '',
                `capacity` VARCHAR(50) DEFAULT '',
                `target_daily_revenue` VARCHAR(50) DEFAULT '',
                PRIMARY KEY (`vehicle_id`),
                KEY `idx_vehicles_investment` (`investment_id`),
                KEY `idx_vehicles_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
    }

    private static function vehicleExpensesSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS `vehicle_expenses` (
                `vehicle_expense_id` INT NOT NULL AUTO_INCREMENT,
                `vehicle_id` INT NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `expense_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `category` VARCHAR(100) DEFAULT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `recorded_by` INT DEFAULT NULL,
                PRIMARY KEY (`vehicle_expense_id`),
                KEY `idx_vehicle_expenses_vehicle` (`vehicle_id`),
                KEY `idx_vehicle_expenses_date` (`expense_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
    }
}
