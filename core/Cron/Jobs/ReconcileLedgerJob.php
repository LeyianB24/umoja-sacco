<?php
declare(strict_types=1);
namespace USMS\Cron\Jobs;

use USMS\Cron\CronJob;

/**
 * ReconcileLedgerJob — Nightly ledger integrity + balance reconciliation.
 * Refactors the logic from cron/reconcile_ledger.php into an OOP class.
 *
 * Schedule: 0 2 * * *  (daily at 02:00)
 * Usage:    php cron/run.php reconcile_ledger [--fix] [--dry-run]
 */
class ReconcileLedgerJob extends CronJob
{
    protected static string $jobName = 'reconcile_ledger';

    protected function handle(array $args): int
    {
        $doFix  = $this->hasFlag('--fix', $args);
        $dryRun = $this->isDryRun($args);
        $errors = 0;
        $checks = 0;

        if ($dryRun) {
            $this->log('[DRY-RUN] Ledger checks will run but NO fixes will be applied.');
        }

        // ── 1. Ensure reconciliation_logs table ───────────────────────────
        $ok = $this->db->query("
            CREATE TABLE IF NOT EXISTS reconciliation_logs (
                log_id            INT AUTO_INCREMENT PRIMARY KEY,
                check_date        DATE NOT NULL,
                account_id        INT,
                account_name      VARCHAR(100),
                ledger_balance    DECIMAL(15,2),
                calculated_balance DECIMAL(15,2),
                difference        DECIMAL(15,2),
                status            ENUM('match','mismatch') DEFAULT 'match',
                created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (check_date)
            ) ENGINE=InnoDB
        ");
        if (!$ok) throw new \Exception("Table creation failed: " . $this->db->error);

        // ── 2. Account internal balance verification ───────────────────────
        $this->log('[1/4] Verifying account internal balance consistency...');
        $res = $this->db->query("
            SELECT la.account_id, la.account_name, la.account_type, la.current_balance,
                SUM(CASE
                    WHEN la.account_type IN ('asset','expense') THEN (le.debit - le.credit)
                    ELSE (le.credit - le.debit)
                END) AS calculated_sum
            FROM ledger_accounts la
            LEFT JOIN ledger_entries le ON la.account_id = le.account_id
            GROUP BY la.account_id
        ");

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $checks++;
                $ledgerBal = (float)$row['current_balance'];
                $calcBal   = (float)($row['calculated_sum'] ?? 0);
                $diff      = round($ledgerBal - $calcBal, 2);

                if (round(abs($diff), 2) > 0.01) {
                    $errors++;
                    $this->log("  [MISMATCH] Account #{$row['account_id']} ({$row['account_name']}): Ledger={$ledgerBal}, Calc={$calcBal}, Diff={$diff}");

                    if ($doFix && !$dryRun) {
                        $u = $this->db->prepare('UPDATE ledger_accounts SET current_balance = ? WHERE account_id = ?');
                        if (!$u) throw new \Exception("Prepare failed (update): " . $this->db->error);
                        $u->bind_param('di', $calcBal, $row['account_id']);
                        $u->execute();
                        $u->close();
                        $this->log("    [FIXED] current_balance set to {$calcBal}");
                    }

                    $ins = $this->db->prepare(
                        "INSERT INTO reconciliation_logs
                            (check_date, account_id, account_name, ledger_balance, calculated_balance, difference, status)
                         VALUES (CURDATE(), ?, ?, ?, ?, ?, 'mismatch')"
                    );
                    if (!$ins) throw new \Exception("Prepare failed (log): " . $this->db->error);
                    $ins->bind_param('isddd', $row['account_id'], $row['account_name'], $ledgerBal, $calcBal, $diff);
                    $ins->execute();
                    $ins->close();
                } else {
                    $this->log("  [OK] Account #{$row['account_id']} ({$row['account_name']}) balance matches.");
                }
            }
        }

        // ── 3. Global trial balance (Dr = Cr) ─────────────────────────────
        $this->log('[2/4] Global trial balance check (ΣDebit = ΣCredit)...');
        $row = $this->db->query('SELECT SUM(debit) AS d, SUM(credit) AS c FROM ledger_entries')->fetch_assoc();
        $totalDr = (float)($row['d'] ?? 0);
        $totalCr = (float)($row['c'] ?? 0);
        $diff    = round($totalDr - $totalCr, 2);
        if (abs($diff) > 0.01) {
            $errors++;
            $this->log("  [CRITICAL] Ledger out of balance! Dr={$totalDr}, Cr={$totalCr}, Diff={$diff}");
        } else {
            $this->log('  [OK] Global ledger is balanced.');
        }

        // ── 4. Per-transaction balance check ──────────────────────────────
        $this->log('[3/4] Per-transaction balance verification...');
        $res = $this->db->query(
            'SELECT transaction_id, SUM(debit) AS d, SUM(credit) AS c
             FROM ledger_entries
             GROUP BY transaction_id
             HAVING ABS(SUM(debit) - SUM(credit)) > 0.01'
        );
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $errors++;
                $this->log("  [IMBALANCE] Txn #{$row['transaction_id']}: Dr={$row['d']}, Cr={$row['c']}");
            }
        } else {
            $this->log('  [OK] All individual transactions are balanced.');
        }

        // ── 5. Loan status consistency ─────────────────────────────────────
        $this->log('[4/4] Loan status consistency check...');
        $res = $this->db->query(
            "SELECT loan_id, current_balance, status FROM loans
             WHERE (current_balance <= 0 AND status != 'completed')
                OR (current_balance > 0 AND status = 'completed')"
        );
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $errors++;
                $this->log("  [MISMATCH] Loan #{$row['loan_id']}: Balance={$row['current_balance']}, Status='{$row['status']}'");
                if ($doFix && !$dryRun) {
                    $newStatus = ($row['current_balance'] <= 0) ? 'completed' : 'disbursed';
                    $this->db->query("UPDATE loans SET status = '{$newStatus}' WHERE loan_id = " . (int) $row['loan_id']);
                    $this->log("    [FIXED] Status → {$newStatus}");
                }
            }
        } else {
            $this->log('  [OK] All loan statuses are consistent.');
        }

        // ── Summary ────────────────────────────────────────────────────────
        $this->log("Reconciliation complete. Checks: {$checks} | Errors: {$errors}");

        if ($errors > 0) {
            error_log("USMS Reconciliation: {$errors} discrepancies found. Run with --fix to auto-correct.");
        }

        return $errors; // Return error count as "records processed"
    }
}
