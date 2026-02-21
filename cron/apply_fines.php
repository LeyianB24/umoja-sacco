<?php
declare(strict_types=1);
/**
 * cron/apply_fines.php — Daily Late-Payment Fine Application
 *
 * Thin entry point. All business logic lives in:
 *   core/Cron/Jobs/DailyFinesJob.php
 *
 * Scheduled: 0 0 * * *  (daily at midnight)
 * Usage: php cron/apply_fines.php [--dry-run]
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Rewrite argv so run.php sees the correct job name
$argv = array_merge(['apply_fines.php', 'daily_fines'], array_slice($argv ?? [], 1));
$argc = count($argv);

require __DIR__ . '/run.php';
