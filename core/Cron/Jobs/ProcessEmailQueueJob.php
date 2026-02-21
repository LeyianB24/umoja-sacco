<?php
declare(strict_types=1);
namespace USMS\Cron\Jobs;

use USMS\Cron\CronJob;

/**
 * ProcessEmailQueueJob — Drain the email_queue table via SMTP.
 * Wraps EmailQueueManager::processPendingEmails().
 *
 * Schedule: * * * * *  (every minute, or every 5 minutes in production)
 * Usage:    php cron/run.php process_email_queue [--batch=20]
 */
class ProcessEmailQueueJob extends CronJob
{
    protected static string $jobName = 'process_email_queue';

    protected function handle(array $args): int
    {
        // Parse optional --batch=N argument
        $batchSize = 20;
        foreach ($args as $arg) {
            if (preg_match('/^--batch=(\d+)$/', $arg, $m)) {
                $batchSize = (int) $m[1];
                break;
            }
        }

        $this->log("Processing email queue (batch size: {$batchSize})...");

        // Check table exists
        $check = $this->db->query("SHOW TABLES LIKE 'email_queue'");
        if (!$check || $check->num_rows === 0) {
            $this->log('[SKIP] email_queue table does not exist yet. Skipping.');
            return 0;
        }

        // Pending count
        $pending = (int) ($this->db->query("SELECT COUNT(*) AS c FROM email_queue WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
        if ($pending === 0) {
            $this->log('No pending emails in queue.');
            return 0;
        }

        $this->log("Found {$pending} pending email(s).");

        // Load EmailQueueManager
        $managerPath = __DIR__ . '/../../../inc/EmailQueueManager.php';
        if (!file_exists($managerPath)) {
            throw new \RuntimeException('EmailQueueManager not found at: ' . $managerPath);
        }
        require_once $managerPath;

        $manager = new \EmailQueueManager($this->db);
        $result  = $manager->processPendingEmails($batchSize);

        $this->log("Result: Sent={$result['sent']}, Failed={$result['failed']}");

        return (int) $result['sent'];
    }
}
