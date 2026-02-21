<?php
declare(strict_types=1);
namespace USMS\Cron;

/**
 * JobRunner — CLI dispatcher for USMS cron jobs.
 *
 * Usage:
 *   php cron/run.php <job_name> [--dry-run] [--fix] [...]
 *
 * Available jobs:
 *   daily_fines           — Apply late-payment fines to overdue loans
 *   reconcile_ledger      — Ledger integrity + balance reconciliation
 *   process_email_queue   — Drain the email_queue table via SMTP
 *   dividend_distribution — Distribute dividends to member accounts
 */
class JobRunner
{
    /** Registry: slug → FQCN */
    private static array $registry = [
        'daily_fines'           => \USMS\Cron\Jobs\DailyFinesJob::class,
        'reconcile_ledger'      => \USMS\Cron\Jobs\ReconcileLedgerJob::class,
        'process_email_queue'   => \USMS\Cron\Jobs\ProcessEmailQueueJob::class,
        'dividend_distribution' => \USMS\Cron\Jobs\DividendDistributionJob::class,
    ];

    /**
     * Run a single named job. Called from cron/run.php.
     *
     * @param  string   $name  Job slug (see registry above)
     * @param  string[] $args  CLI args — pass $argv
     * @param  \mysqli  $db    Database connection
     */
    public static function run(string $name, array $args, \mysqli $db): void
    {
        if (!isset(self::$registry[$name])) {
            $available = implode(', ', array_keys(self::$registry));
            fwrite(STDERR, "[JobRunner] Unknown job '{$name}'. Available: {$available}\n");
            exit(1);
        }

        $class = self::$registry[$name];
        /** @var CronJob $job */
        $job = new $class($db);
        // Strip script name and job name from args, keep flags
        $flags = array_slice($args, 2);
        $job->run($flags);
    }

    /**
     * Run multiple jobs sequentially. Used by worker.php.
     *
     * @param  string[] $jobs Ordered list of job slugs
     * @param  \mysqli  $db
     */
    public static function runAll(array $jobs, \mysqli $db): void
    {
        foreach ($jobs as $name) {
            if (!isset(self::$registry[$name])) {
                fwrite(STDERR, "[JobRunner] Skipping unknown job '{$name}'.\n");
                continue;
            }
            $class = self::$registry[$name];
            /** @var CronJob $job */
            $job = new $class($db);
            // runAll doesn't exit — let each job complete then continue
            try {
                // We call through reflection to avoid exit() in run()
                // Jobs should not exit(0) when called from runAll
                $ref = new \ReflectionMethod($job, 'handle');
                $ref->setAccessible(true);
                $processed = $ref->invoke($job, []);
                echo '[' . date('Y-m-d H:i:s') . "] [{$name}] Processed {$processed} records.\n";
            } catch (\Throwable $e) {
                fwrite(STDERR, "[JobRunner] Job '{$name}' failed: " . $e->getMessage() . "\n");
                error_log("USMS JobRunner [{$name}] ERROR: " . $e->getMessage());
            }
        }
    }

    /** Print available jobs to STDOUT */
    public static function listJobs(): void
    {
        echo "Available USMS cron jobs:\n";
        foreach (self::$registry as $slug => $class) {
            echo "  {$slug}\n";
        }
    }
}
