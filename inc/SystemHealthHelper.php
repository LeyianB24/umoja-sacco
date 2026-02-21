<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db_connect.php';

function getSystemHealth($conn) {
    $health = [
        'pending_transactions' => 0,
        'failed_callbacks' => 0,
        'failed_notifications' => 0,
        'ledger_imbalance' => false,
        'daily_volume' => 0,
        'callback_success_rate' => 100
    ];

    // 1. Pending Transactions (> 5 mins)
    $q = $conn->query("SELECT COUNT(*) as count FROM mpesa_requests WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $health['pending_transactions'] = $q->fetch_assoc()['count'] ?? 0;

    // 2. Failed Callbacks (Today)
    $q = $conn->query("SELECT COUNT(*) as count FROM callback_logs WHERE result_code != 0 AND created_at >= CURDATE()");
    $health['failed_callbacks'] = $q->fetch_assoc()['count'] ?? 0;

    // 3. Failed Notifications
    $q = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE delivery_status = 'failed' AND created_at >= CURDATE()");
    $health['failed_notifications'] = $q->fetch_assoc()['count'] ?? 0;

    // 4. Ledger Imbalance Check
    $q = $conn->query("SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM ledger_entries");
    $row = $q->fetch_assoc();
    if (abs(($row['total_debit'] ?? 0) - ($row['total_credit'] ?? 0)) > 0.01) {
        $health['ledger_imbalance'] = true;
    }

    // 5. Daily Volume (Successful STK + B2C)
    $q = $conn->query("SELECT SUM(amount) as total FROM callback_logs WHERE processed = TRUE AND created_at >= CURDATE()");
    $health['daily_volume'] = (float)($q->fetch_assoc()['total'] ?? 0);

    // 6. Callback Success Rate
    $q = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN result_code = 0 THEN 1 ELSE 0 END) as success
        FROM callback_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $row = $q->fetch_assoc();
    if ($row['total'] > 0) {
        $health['callback_success_rate'] = round(($row['success'] / $row['total']) * 100, 1);
    }

    return $health;
}
