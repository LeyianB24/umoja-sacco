<?php
declare(strict_types=1);

namespace USMS\Database\Migrations;

use USMS\Database\Migration;

/**
 * Create query performance logging table
 */
class create_query_log_table extends Migration
{
    public function up(\mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS query_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sql TEXT NOT NULL,
                execution_time DECIMAL(10, 4),
                parameters JSON,
                is_error BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_execution_time (execution_time),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $conn->query($sql);
    }
    
    public function down(\mysqli $conn): void
    {
        $conn->query("DROP TABLE IF EXISTS query_logs");
    }
}
