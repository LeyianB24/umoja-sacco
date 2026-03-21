<?php
declare(strict_types=1);
namespace USMS\Cron;

/**
 * CronJob — Abstract Base for all USMS Background Jobs
 *
 * Every concrete job must:
 *   1. Extend this class
 *   2. Set static $jobName (e.g. 'daily_fines')
 *   3. Implement handle(array $args): int  (returns records processed)
 *
 * Features provided automatically:
 *   - CLI-only guard (dies gracefully if hit from a browser)
 *   - Exclusive lock file — prevents overlapping runs
 *   - DB heartbeat — inserts a row in `cron_runs` on start, updates on finish
 *   - Timestamped console logging via log()
 *   - Total wall-clock timing
 *   - Graceful exception handling (job fails cleanly, lock released)
 */
abstract class CronJob
{
    /** Override in each concrete job */
    protected static string $jobName = 'base';

    protected \mysqli $db;
    /** @var resource|null Lock file handle */
    private $lockHandle = null;
    private string $lockPath = '';
    private int $runId = 0;
    private float $startTime = 0.0;

    // ── Boot ──────────────────────────────────────────────────────────────────

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Entry point — called by JobRunner.
     */
    final public function run(array $args = []): void
    {
        $this->enforceCliOnly();

        $this->startTime = microtime(true);
        $name = static::$jobName;

        $this->log("=== Starting job: {$name} ===");

        // Acquire lock
        if (!$this->acquireLock()) {
            $this->log("[SKIP] Another instance of '{$name}' is already running (lock file present).");
            exit(0);
        }

        // DB heartbeat: start
        $this->runId = $this->dbInsertStart($name);

        $exitCode = 0;
        $processed = 0;
        $output = '';

        try {
            ob_start();
            $processed = $this->handle($args);
            $output = (string) ob_get_clean();

            $elapsed = (int) round((microtime(true) - $this->startTime) * 1000);
            $this->log("=== Finished: {$processed} record(s) processed in {$elapsed}ms ===");
            $this->dbUpdateFinish($this->runId, 'success', $processed, $output, $elapsed);
        } catch (\Throwable $e) {
            ob_end_clean();
            $exitCode = 1;
            $msg = get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            $this->log("[ERROR] {$msg}");
            error_log("USMS Cron [{$name}] FAILED — {$msg}");

            $elapsed = (int) round((microtime(true) - $this->startTime) * 1000);
            $this->dbUpdateFinish($this->runId, 'failed', 0, $msg, $elapsed);
        } finally {
            $this->releaseLock();
        }

        exit($exitCode);
    }

    /**
     * Concrete jobs implement their logic here.
     * @param  array $args CLI arguments (e.g. ['--fix', '--dry-run'])
     * @return int  Number of records processed
     */
    abstract protected function handle(array $args): int;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Timestamped console output + PHP error log
     */
    protected function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [' . static::$jobName . '] ' . $message;
        echo $line . PHP_EOL;
        error_log($line);
    }

    protected function isDryRun(array $args): bool
    {
        return in_array('--dry-run', $args, true);
    }

    protected function hasFlag(string $flag, array $args): bool
    {
        return in_array($flag, $args, true);
    }

    // ── CLI Guard ─────────────────────────────────────────────────────────────

    private function enforceCliOnly(): void
    {
        if (php_sapi_name() !== 'cli') {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'This script must be run from the command line only.' . PHP_EOL;
            exit(1);
        }
    }

    // ── Lock File ─────────────────────────────────────────────────────────────

    private function acquireLock(): bool
    {
        $tmpDir = sys_get_temp_dir();
        $this->lockPath = $tmpDir . DIRECTORY_SEPARATOR . 'usms_cron_' . static::$jobName . '.lock';

        $this->lockHandle = fopen($this->lockPath, 'c');
        if ($this->lockHandle === false) {
            $this->log("[WARN] Could not open lock file: {$this->lockPath} — running without lock.");
            return true; // Fail open — still run
        }

        if (!flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($this->lockHandle);
            $this->lockHandle = null;
            return false; // Locked by another process
        }

        ftruncate($this->lockHandle, 0);
        fwrite($this->lockHandle, (string) getmypid());

        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
            @unlink($this->lockPath);
        }
    }

    // ── DB Heartbeat ─────────────────────────────────────────────────────────

    private function dbInsertStart(string $name): int
    {
        // Silently skip if table doesn't exist yet (before migration runs)
        try {
            $stmt = @$this->db->prepare(
                "INSERT INTO cron_runs (job_name, status, started_at) VALUES (?, 'running', NOW())"
            );
            if (!$stmt) return 0;
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $id = (int) $this->db->insert_id;
            $stmt->close();
            return $id;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function dbUpdateFinish(int $id, string $status, int $processed, string $output, int $ms): void
    {
        if ($id === 0) return;
        try {
            $stmt = @$this->db->prepare(
                "UPDATE cron_runs
                 SET status = ?, finished_at = NOW(), duration_ms = ?, records_processed = ?, output = ?
                 WHERE id = ?"
            );
            if (!$stmt) return;
            $stmt->bind_param('sisis', $status, $ms, $processed, $output, $id);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {
            // Silently ignore — don't let tracking failure break the job
        }
    }
}
