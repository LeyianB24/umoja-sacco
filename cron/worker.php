<?php
declare(strict_types=1);
/**
 * cron/worker.php — Background Worker (Multi-Job Pipeline)
 *
 * Runs all queue-draining jobs sequentially in one invocation.
 * Strict CLI-only — will NOT run from a browser.
 *
 * Scheduled: every minute (or every 5 minutes in production)
 * Usage: php cron/worker.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo '403 Forbidden — This script must be run from the command line.' . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$runner = \USMS\Cron\JobRunner::class;

echo '[' . date('Y-m-d H:i:s') . "] === USMS Background Worker Starting ===\n";

\USMS\Cron\JobRunner::runAll([
    'process_email_queue',
], $conn);

echo '[' . date('Y-m-d H:i:s') . "] === Worker finished ===\n";
echo str_repeat('-', 50) . "\n";
