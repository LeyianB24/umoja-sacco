import { prisma } from '../lib/prisma';
import { JobArgs, JobDefinition, JobResult } from './types';
import { runDailyFines } from './daily-fines';
import { runRepaymentReminders } from './repayment-reminders';
import { runLateReminders } from './late-reminders';
import { runReconcileLedger } from './reconcile-ledger';
import { runDividendDistribution } from './dividend-distribution';
import { runProcessEmailQueue } from './process-email-queue';

export const REGISTERED_JOBS: Record<string, JobDefinition> = {
  daily_fines: {
    name: 'daily_fines',
    description: 'Apply daily 0.05% late fines to all overdue loans and record ledger transactions',
    schedule: '0 0 * * * (Daily at midnight)',
    run: runDailyFines,
  },
  apply_fines: {
    name: 'apply_fines',
    description: 'Alias for daily_fines',
    schedule: '0 0 * * * (Daily at midnight)',
    run: runDailyFines,
  },
  repayment_reminders: {
    name: 'repayment_reminders',
    description: 'Send repayment reminder notifications for loans due in 3 days',
    schedule: '0 9 * * * (Daily at 9:00 AM)',
    run: runRepaymentReminders,
  },
  late_reminders: {
    name: 'late_reminders',
    description: 'Send urgent late payment reminder notifications to all overdue members',
    schedule: '0 10 * * 1 (Weekly on Mondays)',
    run: runLateReminders,
  },
  reconcile_ledger: {
    name: 'reconcile_ledger',
    description: 'Verify double-entry ledger balance integrity and trial balance equation',
    schedule: '0 2 * * * (Daily at 2:00 AM)',
    run: runReconcileLedger,
  },
  dividend_distribution: {
    name: 'dividend_distribution',
    description: 'Calculate and distribute annual member dividends based on savings and surplus',
    schedule: '0 3 1 1 * (Annually on Jan 1st)',
    run: runDividendDistribution,
  },
  process_email_queue: {
    name: 'process_email_queue',
    description: 'Drain pending outbound email queue via Nodemailer SMTP',
    schedule: '*/5 * * * * (Every 5 minutes)',
    run: runProcessEmailQueue,
  },
};

export function parseArgs(rawArgs: string[]): { jobName: string; jobArgs: JobArgs } {
  const args = rawArgs.slice(2);
  const jobName = args[0] || '';

  const jobArgs: JobArgs = {
    dryRun: args.includes('--dry-run'),
    fix: args.includes('--fix'),
  };

  for (const arg of args) {
    if (arg.startsWith('--batch=')) {
      jobArgs.batchSize = parseInt(arg.split('=')[1], 10);
    }
    if (arg.startsWith('--period=')) {
      jobArgs.period = parseInt(arg.split('=')[1], 10);
    }
  }

  return { jobName, jobArgs };
}

export async function executeJob(jobName: string, args: JobArgs = {}): Promise<JobResult> {
  const job = REGISTERED_JOBS[jobName];
  if (!job) {
    throw new Error(`Unknown job "${jobName}". Use --list to see available jobs.`);
  }

  console.log(`\n======================================================`);
  console.log(`▶ EXECUTING USMS JOB: [${job.name}]`);
  console.log(`▶ Description: ${job.description}`);
  console.log(`▶ Schedule: ${job.schedule}`);
  console.log(`▶ Parameters: ${JSON.stringify(args)}`);
  console.log(`======================================================\n`);

  const result = await job.run(args);

  // Log execution in cron_logs table
  await prisma.cronLogs.create({
    data: {
      job_name: job.name,
      status: result.success ? 'success' : 'failed',
      message: result.message,
      duration_ms: result.durationMs,
      records_processed: result.recordsProcessed,
    },
  }).catch(() => null);

  console.log(`\n======================================================`);
  console.log(`✔ JOB FINISHED: ${job.name} -> ${result.success ? 'SUCCESS' : 'FAILED'}`);
  console.log(`✔ Records Processed: ${result.recordsProcessed}`);
  console.log(`✔ Duration: ${result.durationMs}ms`);
  console.log(`======================================================\n`);

  return result;
}

async function main() {
  const { jobName, jobArgs } = parseArgs(process.argv);

  if (!jobName || jobName === '--list' || jobName === 'list' || jobName === '--help' || jobName === 'help') {
    console.log(`\n=== Umoja SACCO Automation Job Engine (USMS) ===\n`);
    console.log(`Usage:`);
    console.log(`  npm run job -- <job_name> [options]\n`);
    console.log(`Options:`);
    console.log(`  --dry-run       Simulate calculations without mutating database state`);
    console.log(`  --fix           Auto-correct ledger balance discrepancies`);
    console.log(`  --period=YYYY   Target specific fiscal year (e.g. --period=2025)`);
    console.log(`  --batch=N       Limit batch processing size (default 20)\n`);
    console.log(`Available Jobs:`);
    for (const [key, def] of Object.entries(REGISTERED_JOBS)) {
      console.log(`  • ${key.padEnd(24)} | ${def.schedule.padEnd(30)} | ${def.description}`);
    }
    console.log('');
    process.exit(0);
  }

  try {
    const result = await executeJob(jobName, jobArgs);
    process.exit(result.success ? 0 : 1);
  } catch (err: any) {
    console.error(`\n✖ FATAL JOB ERROR:`, err.message);
    process.exit(1);
  } finally {
    await prisma.$disconnect();
  }
}

if (require.main === module) {
  main();
}
