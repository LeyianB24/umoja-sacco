<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/app.php';

echo "\nRunning stats query...\n";
$stats_q = $conn->query("SELECT 
    COUNT(TABLE_NAME) as tables, 
    SUM(TABLE_ROWS) as total_rows, 
    SUM(DATA_LENGTH + INDEX_LENGTH) as total_bytes 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE()");
if (!$stats_q) {
    echo "Stats query failed: " . $conn->error . "\n";
} else {
    $stats = $stats_q->fetch_assoc();
    print_r($stats);
}

echo "\nRunning backup logs query...\n";
$backup_logs = $conn->query("SELECT a.*, ad.full_name as admin_name 
                          FROM audit_logs a 
                          LEFT JOIN admins ad ON a.admin_id = ad.admin_id
                          WHERE a.action LIKE '%BACKUP_SUCCESS%' 
                          ORDER BY a.created_at DESC LIMIT 10");
if (!$backup_logs) {
    echo "Backup logs query failed: " . $conn->error . "\n";
} else {
    echo "Backup logs query success. Rows: " . $backup_logs->num_rows . "\n";
}
