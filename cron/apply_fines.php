<?php
// usms/cron/apply_fines.php
// To be executed daily via system cron (e.g., 0 0 * * *)

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/CronHelper.php';

echo "[CRON] Starting Daily Fines processing at " . date('Y-m-d H:i:s') . "\n";

$cronHelper = new CronHelper($conn);
$count = $cronHelper->applyDailyFines();

echo "[CRON] Completed. Fines applied to $count overdue loans.\n";
