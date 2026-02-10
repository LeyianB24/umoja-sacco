<?php
/**
 * Cron Job: Background Worker
 * Processes email/SMS queues and runs automated health checks
 */

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/EmailQueueManager.php';
require_once __DIR__ . '/../inc/TransactionMonitor.php';
require_once __DIR__ . '/../inc/FinancialIntegrityChecker.php';

// CLI ONLY check (optional but recommended)
// if (php_sapi_name() !== 'cli') exit("This script must be run from the command line.");

echo "[" . date('Y-m-d H:i:s') . "] Starting Background Worker...\n";

// 1. Process Email Queue
echo "Processing Email Queue...\n";
$emailManager = new EmailQueueManager($conn);
$emailResults = $emailManager->processPendingEmails(20);
echo "Emails: Sent {$emailResults['sent']}, Failed {$emailResults['failed']}\n";

// 2. Run Transaction Monitor Health Check
echo "Running Transaction Health Check...\n";
$monitor = new TransactionMonitor($conn);
$alertsTriggered = $monitor->runHealthCheck();
echo "Stuck Transaction Alerts: $alertsTriggered\n";

// 3. Run Financial Integrity Audit (Once per hour or per run)
echo "Running Financial Integrity Audit...\n";
$checker = new FinancialIntegrityChecker($conn);
$auditResults = $checker->runFullAudit();
echo "Integrity: Sync Status '{$auditResults['sync']['status']}', Balance Status '{$auditResults['balance']['status']}'\n";

// 4. (TBD) Process SMS Queue
// echo "Processing SMS Queue...\n";
// $smsManager = new SMSQueueManager($conn);
// $smsResults = $smsManager->processPendingSMS(20);

echo "[" . date('Y-m-d H:i:s') . "] Worker finished.\n";
echo str_repeat("-", 40) . "\n";
