<?php
declare(strict_types=1);
/**
 * cron/run.php — Universal USMS Job Dispatcher (CLI only)
 *
 * Usage:
 *   php cron/run.php <job_name> [flags]
 *
 * Examples:
 *   php cron/run.php daily_fines
 *   php cron/run.php daily_fines --dry-run
 *   php cron/run.php reconcile_ledger --fix
 *   php cron/run.php dividend_distribution --period=2025 --dry-run
 *   php cron/run.php process_email_queue --batch=50
 *
 * Crontab examples (Linux/XAMPP scheduler):
 *   0 0 * * * php /var/www/usms/cron/run.php daily_fines >> /var/log/usms/cron.log 2>&1
 *   0 2 * * * php /var/www/usms/cron/run.php reconcile_ledger >> /var/log/usms/cron.log 2>&1
 *   * * * * * php /var/www/usms/cron/run.php process_email_queue --batch=20 >> /var/log/usms/cron.log 2>&1
 *   0 3 1 1 * php /var/www/usms/cron/run.php dividend_distribution >> /var/log/usms/cron.log 2>&1
 */

// Strict CLI-only enforcement
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo '403 Forbidden — This script must be run from the command line.' . PHP_EOL;
    exit(1);
}

// Bootstrap
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Composer autoloader registers USMS\Cron namespace
if (!class_exists(\USMS\Cron\JobRunner::class)) {
    fwrite(STDERR, "ERROR: Autoloader not configured. Run: composer dump-autoload -o\n");
    exit(1);
}

use USMS\Cron\JobRunner;

// Parse arguments
$jobName = $argv[1] ?? '';

if ($jobName === '' || $jobName === '--list' || $jobName === 'list') {
    JobRunner::listJobs();
    exit(0);
}

if ($jobName === '--help' || $jobName === 'help') {
    echo "Usage: php cron/run.php <job_name> [--dry-run] [--fix] [--batch=N] [--period=YYYY]\n\n";
    JobRunner::listJobs();
    exit(0);
}

// Dispatch
JobRunner::run($jobName, $argv, $conn);
