<?php
declare(strict_types=1);
namespace USMS\Cron\Jobs;

use USMS\Cron\CronJob;

/**
 * RepaymentRemindersJob — Send email reminders for upcoming loan repayments.
 * Wraps CronHelper::sendRepaymentReminders().
 *
 * Schedule: 0 9 * * *  (daily at 9 AM)
 * Usage:    php cron/run.php repayment_reminders
 */
class RepaymentRemindersJob extends CronJob
{
    protected static string $jobName = 'repayment_reminders';

    protected function handle(array $args): int
    {
        // Load CronHelper
        $helperPath = __DIR__ . '/../../../inc/CronHelper.php';
        require_once $helperPath;

        $helper = new \CronHelper($this->db);
        
        $this->log("Checking for upcoming repayments due in 3 days...");
        $processed = $helper->sendRepaymentReminders();
        
        $this->log("Sent reminders to {$processed} member(s).");
        return $processed;
    }
}
