<?php
// core/exports/setup_export_db.php
require_once __DIR__ . '/../../config/db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS export_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_role VARCHAR(50) NULL,
    module VARCHAR(100) NOT NULL,
    export_type VARCHAR(20) NOT NULL,
    exported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    record_count INT DEFAULT 0,
    total_value DECIMAL(15, 2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'success',
    details TEXT NULL,
    INDEX (user_id),
    INDEX (module),
    INDEX (exported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'export_logs' created successfully or already exists.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
