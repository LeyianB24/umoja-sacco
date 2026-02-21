<?php
declare(strict_types=1);
/**
 * cron/reconcile_ledger.php — Ledger Reconciliation Entry Point
 *
 * Thin entry point. All logic lives in:
 *   core/Cron/Jobs/ReconcileLedgerJob.php
 *
 * Scheduled: 0 2 * * *  (daily at 02:00)
 * Usage: php cron/reconcile_ledger.php [--fix] [--dry-run]
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Rewrite argv so run.php dispatches the correct job
$argv = array_merge(['reconcile_ledger.php', 'reconcile_ledger'], array_slice($argv ?? [], 1));
$argc = count($argv);

require __DIR__ . '/run.php';
