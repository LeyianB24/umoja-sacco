<?php
require_once 'config/app.php';

$sql = "ALTER TABLE audit_logs 
        ADD COLUMN user_id INT NULL AFTER admin_id,
        ADD COLUMN user_type VARCHAR(50) NULL AFTER user_id,
        ADD COLUMN severity VARCHAR(50) DEFAULT 'info' AFTER user_type,
        ADD COLUMN user_agent VARCHAR(500) NULL AFTER ip_address";

if ($conn->query($sql)) {
    echo "Successfully updated audit_logs table schema.\n";
} else {
    echo "Error updating schema: " . $conn->error . "\n";
}
