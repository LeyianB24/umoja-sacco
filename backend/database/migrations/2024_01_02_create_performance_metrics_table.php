<?php
declare(strict_types=1);

namespace USMS\Database\Migrations;

use USMS\Database\Migration;

/**
 * Create system performance metrics table
 */
class create_performance_metrics_table extends Migration
{
    public function up(\mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS performance_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_type VARCHAR(100) NOT NULL,
                metric_value DECIMAL(15, 4),
                unit VARCHAR(50),
                metadata JSON,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type (metric_type),
                INDEX idx_recorded_at (recorded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $conn->query($sql);
    }
    
    public function down(\mysqli $conn): void
    {
        $conn->query("DROP TABLE IF EXISTS performance_metrics");
    }
}
