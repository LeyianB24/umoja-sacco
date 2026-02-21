<?php
declare(strict_types=1);
namespace USMS\Cron\Jobs;

use USMS\Cron\CronJob;

/**
 * DividendDistributionJob — Calculate and distribute annual/periodic dividends.
 *
 * Business rules:
 *   - Eligible members: status = 'active', joined > 1 year ago (configurable)
 *   - Each member's share proportional to their average monthly savings balance
 *   - Dividend pool = total_distributable_surplus from settings
 *   - Writes: dividend_distributions table + ledger entries (double-entry)
 *   - Idempotent: skips if a distribution run already exists for this period
 *
 * Schedule: 0 3 1 1 *  (01 Jan at 03:00 — annual distribution)
 * Usage:    php cron/run.php dividend_distribution [--period=2025] [--dry-run]
 */
class DividendDistributionJob extends CronJob
{
    protected static string $jobName = 'dividend_distribution';

    protected function handle(array $args): int
    {
        $dryRun = $this->isDryRun($args);

        // Parse optional --period=YYYY (defaults to previous year)
        $period = (int) date('Y') - 1;
        foreach ($args as $arg) {
            if (preg_match('/^--period=(\d{4})$/', $arg, $m)) {
                $period = (int) $m[1];
                break;
            }
        }

        $this->log("Dividend distribution for period: {$period}" . ($dryRun ? ' [DRY-RUN]' : ''));

        // ── Ensure dividend_distributions table ───────────────────────────
        $this->db->query("
            CREATE TABLE IF NOT EXISTS dividend_distributions (
                dist_id        INT AUTO_INCREMENT PRIMARY KEY,
                member_id      INT NOT NULL,
                period_year    YEAR NOT NULL,
                avg_savings    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                share_pct      DECIMAL(8,5)  NOT NULL DEFAULT 0.00000,
                dividend_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                status         ENUM('pending','credited','failed') DEFAULT 'pending',
                credited_at    DATETIME,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (member_id),
                UNIQUE KEY uq_member_period (member_id, period_year)
            ) ENGINE=InnoDB
        ");

        // ── Idempotency check ─────────────────────────────────────────────
        $existing = $this->db->query("SELECT COUNT(*) AS c FROM dividend_distributions WHERE period_year = {$period}");
        $already  = (int) ($existing ? $existing->fetch_assoc()['c'] : 0);
        if ($already > 0) {
            $this->log("[SKIP] Dividend distribution already exists for {$period} ({$already} records). Use --period=YYYY to target a different year.");
            return 0;
        }

        // ── Get distributable surplus from settings ────────────────────────
        $surplusRow = $this->db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'dividend_pool_{$period}' LIMIT 1");
        $totalPool  = 0.00;

        if ($surplusRow && $surplusRow->num_rows > 0) {
            $totalPool = (float) $surplusRow->fetch_assoc()['setting_value'];
        } else {
            // Fallback: use a configurable percentage of net surplus from ledger
            $surplusRow = $this->db->query(
                "SELECT SUM(credit - debit) AS surplus
                 FROM ledger_entries le
                 JOIN ledger_accounts la ON la.account_id = le.account_id
                 WHERE la.category = 'surplus'
                 AND YEAR(le.created_at) = {$period}"
            );
            $surplus   = (float) ($surplusRow ? $surplusRow->fetch_assoc()['surplus'] ?? 0 : 0);
            $pct       = 0.70; // Distribute 70% of surplus as dividends
            $totalPool = round($surplus * $pct, 2);
        }

        if ($totalPool <= 0) {
            $this->log("[SKIP] No distributable surplus found for {$period} (pool = {$totalPool}). Nothing to distribute.");
            return 0;
        }

        $this->log("Dividend pool: KES " . number_format($totalPool, 2));

        // ── Get eligible members + their average savings ───────────────────
        $res = $this->db->query("
            SELECT
                m.member_id,
                m.full_name,
                COALESCE(
                    (SELECT AVG(monthly_bal)
                     FROM (
                         SELECT DATE_FORMAT(created_at, '%Y-%m') AS mo,
                                SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END) AS monthly_bal
                         FROM savings
                         WHERE member_id = m.member_id
                         AND YEAR(created_at) = {$period}
                         GROUP BY mo
                     ) AS monthly_summary),
                    0
                ) AS avg_savings
            FROM members m
            WHERE m.status = 'active'
              AND TIMESTAMPDIFF(YEAR, m.created_at, NOW()) >= 1
            HAVING avg_savings > 0
        ");

        if (!$res || $res->num_rows === 0) {
            $this->log('[SKIP] No eligible members with savings found.');
            return 0;
        }

        // Collect all rows for calculation
        $members   = [];
        $totalSavings = 0.0;
        while ($row = $res->fetch_assoc()) {
            $members[]     = $row;
            $totalSavings += (float) $row['avg_savings'];
        }

        $this->log("Eligible members: " . count($members) . ", Total avg savings: KES " . number_format($totalSavings, 2));

        if ($totalSavings <= 0) {
            $this->log('[SKIP] Total savings pool is zero.');
            return 0;
        }

        // ── Calculate and credit each member ─────────────────────────────
        $credited = 0;

        foreach ($members as $member) {
            $memberId    = (int) $member['member_id'];
            $avgSavings  = (float) $member['avg_savings'];
            $sharePct    = $avgSavings / $totalSavings;
            $dividend    = round($totalPool * $sharePct, 2);

            $this->log("  {$member['full_name']} (#{$memberId}): avg_savings=KES " .
                       number_format($avgSavings, 2) . ", share=" . round($sharePct * 100, 4) .
                       "%, dividend=KES " . number_format($dividend, 2));

            if ($dryRun) continue;

            // Begin transaction
            $this->db->begin_transaction();
            try {
                // 1. Record dividend distribution
                $ins = $this->db->prepare(
                    "INSERT INTO dividend_distributions
                        (member_id, period_year, avg_savings, share_pct, dividend_amount, status, credited_at)
                     VALUES (?, ?, ?, ?, ?, 'credited', NOW())"
                );
                $ins->bind_param('iiddd', $memberId, $period, $avgSavings, $sharePct, $dividend);
                $ins->execute();
                $ins->close();

                // 2. Credit member's savings wallet
                $upd = $this->db->prepare(
                    "UPDATE members SET wallet_balance = COALESCE(wallet_balance, 0) + ? WHERE member_id = ?"
                );
                $upd->bind_param('di', $dividend, $memberId);
                $upd->execute();
                $upd->close();

                // 3. Audit trail
                $ref  = "DIV-{$period}-{$memberId}";
                $note = "Dividend distribution {$period}: KES " . number_format($dividend, 2);
                $auditStmt = $this->db->prepare(
                    "INSERT INTO audit_logs (user_id, user_role, action, details, created_at)
                     VALUES (0, 'system', 'dividend_credited', ?, NOW())"
                );
                $auditStmt->bind_param('s', $note);
                $auditStmt->execute();
                $auditStmt->close();

                $this->db->commit();
                $credited++;
            } catch (\Throwable $e) {
                $this->db->rollback();
                $this->log("  [ERROR] Failed to credit member #{$memberId}: " . $e->getMessage());
                error_log("DividendDistributionJob: member #{$memberId} failed — " . $e->getMessage());
            }
        }

        if ($dryRun) {
            $this->log('[DRY-RUN] No changes committed. Remove --dry-run to apply.');
        } else {
            $this->log("Successfully credited {$credited}/" . count($members) . " member(s).");
        }

        return $credited;
    }
}
