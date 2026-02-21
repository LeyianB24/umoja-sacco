<?php
declare(strict_types=1);
namespace USMS\Cron\Jobs;

use USMS\Cron\CronJob;

/**
 * DailyFinesJob — Apply late-payment fines to all overdue loans.
 * Wraps the existing CronHelper::applyDailyFines() business logic.
 *
 * Schedule: 0 0 * * *  (daily at midnight)
 * Usage:    php cron/run.php daily_fines [--dry-run]
 */
class DailyFinesJob extends CronJob
{
    protected static string $jobName = 'daily_fines';

    protected function handle(array $args): int
    {
        $dryRun = $this->isDryRun($args);

        if ($dryRun) {
            $this->log('[DRY-RUN] Would apply daily fines — no changes written.');
        }

        // Load CronHelper (legacy class, not yet namespaced)
        $helperPath = __DIR__ . '/../../../inc/CronHelper.php';
        if (!file_exists($helperPath)) {
            throw new \RuntimeException('CronHelper not found at: ' . $helperPath);
        }
        require_once $helperPath;

        $helper = new \CronHelper($this->db);

        if ($dryRun) {
            // Identify overdue loans count without writing anything
            $today = date('Y-m-d');
            $res = $this->db->query(
                "SELECT COUNT(*) AS c FROM loans l
                 WHERE l.status IN ('active','disbursed')
                 AND l.next_repayment_date < '{$today}'
                 AND NOT EXISTS (SELECT 1 FROM fines f WHERE f.loan_id = l.loan_id AND f.date_applied = '{$today}')"
            );
            $count = (int) ($res ? $res->fetch_assoc()['c'] : 0);
            $this->log("[DRY-RUN] {$count} loan(s) would receive a fine today.");
            return 0;
        }

        $processed = $helper->applyDailyFines();
        $this->log("Fines applied to {$processed} overdue loan(s).");
        return $processed;
    }
}
