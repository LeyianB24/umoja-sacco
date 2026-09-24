import { prisma } from '../lib/prisma';
import { JobArgs, JobResult } from './types';

export async function runReconcileLedger(args: JobArgs = {}): Promise<JobResult> {
  const startTime = Date.now();
  const dryRun = Boolean(args.dryRun);
  const doFix = Boolean(args.fix);

  console.log(`[reconcile_ledger] Starting ledger reconciliation... ${dryRun ? '[DRY-RUN]' : ''} ${doFix ? '[AUTO-FIX ENABLED]' : ''}`);

  const accounts = await prisma.ledgerAccounts.findMany();
  console.log(`[reconcile_ledger] [1/3] Verifying ${accounts.length} ledger accounts...`);

  let mismatches = 0;
  let fixedCount = 0;

  for (const acc of accounts) {
    const entriesAgg = await prisma.ledgerEntries.aggregate({
      where: { account_id: acc.account_id },
      _sum: {
        debit: true,
        credit: true,
      },
    });

    const totalDebit = Number(entriesAgg._sum.debit || 0);
    const totalCredit = Number(entriesAgg._sum.credit || 0);

    const type = (acc.account_type || '').toLowerCase();
    const isDebitNormal = type === 'asset' || type === 'expense';
    const calcSum = isDebitNormal ? totalDebit - totalCredit : totalCredit - totalDebit;
    const ledgerBalance = Number(acc.current_balance || 0);
    const diff = Math.round((ledgerBalance - calcSum) * 100) / 100;

    if (Math.abs(diff) > 0.01) {
      mismatches++;
      console.warn(`  [MISMATCH] Account #${acc.account_id} (${acc.account_name}): Ledger = KES ${ledgerBalance}, Calc = KES ${calcSum}, Diff = KES ${diff}`);

      // Log in reconciliation_logs
      if (!dryRun) {
        await prisma.reconciliationLogs.create({
          data: {
            account_id: acc.account_id,
            ledger_sum: calcSum,
            account_balance: ledgerBalance,
            difference: diff,
            status: 'mismatch',
          },
        }).catch((err) => console.error('Failed to write reconciliation log:', err.message));

        if (doFix) {
          await prisma.ledgerAccounts.update({
            where: { account_id: acc.account_id },
            data: { current_balance: calcSum },
          });
          fixedCount++;
          console.log(`    [FIXED] Account #${acc.account_id} current_balance corrected to KES ${calcSum}`);
        }
      }
    } else {
      console.log(`  [OK] Account #${acc.account_id} (${acc.account_name}) balance matches.`);
    }
  }

  // 2. Global Trial Balance Check (Sum Debit == Sum Credit)
  console.log(`[reconcile_ledger] [2/3] Checking global trial balance double-entry integrity...`);
  const globalAgg = await prisma.ledgerEntries.aggregate({
    _sum: {
      debit: true,
      credit: true,
    },
  });

  const globalDebit = Number(globalAgg._sum.debit || 0);
  const globalCredit = Number(globalAgg._sum.credit || 0);
  const globalDiff = Math.round((globalDebit - globalCredit) * 100) / 100;
  const trialBalanced = Math.abs(globalDiff) <= 0.01;

  console.log(`  Total Debits: KES ${globalDebit.toFixed(2)} | Total Credits: KES ${globalCredit.toFixed(2)} | Imbalance: KES ${globalDiff.toFixed(2)}`);

  // 3. Record Integrity Check
  if (!dryRun) {
    await prisma.integrityChecks.create({
      data: {
        check_type: 'trial_balance',
        status: trialBalanced ? 'passed' : 'failed',
        details: JSON.stringify({
          accounts_audited: accounts.length,
          mismatches_found: mismatches,
          accounts_fixed: fixedCount,
          total_debits: globalDebit,
          total_credits: globalCredit,
          imbalance: globalDiff,
        }),
      },
    }).catch(() => null);
  }

  const durationMs = Date.now() - startTime;
  const message = `Ledger Reconciliation Complete: ${accounts.length} accounts verified. Mismatches: ${mismatches}, Auto-fixed: ${fixedCount}. Trial Balance: ${trialBalanced ? 'BALANCED' : 'IMBALANCED'}. Duration: ${durationMs}ms`;
  console.log(`[reconcile_ledger] ${message}`);

  return {
    jobName: 'reconcile_ledger',
    success: mismatches === 0 || (doFix && mismatches === fixedCount),
    recordsProcessed: accounts.length,
    message,
    durationMs,
    details: {
      accounts_audited: accounts.length,
      mismatches,
      fixed: fixedCount,
      trialBalanced,
      globalDebit,
      globalCredit,
    },
  };
}
