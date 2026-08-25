import { executeJob } from './runner';
import { prisma } from '../lib/prisma';

async function runWorkerCycle() {
  console.log(`\n[${new Date().toISOString()}] === USMS Background Worker Starting Cycle ===`);
  try {
    // 1. Process outbound emails
    await executeJob('process_email_queue', { batchSize: 30 }).catch((e) => console.error('Worker email error:', e.message));

    // 2. Check time for scheduled daily runs
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();

    // At 00:05 AM -> Run daily fines
    if (currentHour === 0 && currentMinute >= 5 && currentMinute <= 9) {
      await executeJob('daily_fines').catch((e) => console.error('Worker daily fines error:', e.message));
    }

    // At 02:00 AM -> Run ledger reconciliation
    if (currentHour === 2 && currentMinute >= 0 && currentMinute <= 4) {
      await executeJob('reconcile_ledger', { fix: true }).catch((e) => console.error('Worker reconciliation error:', e.message));
    }

    // At 09:00 AM -> Run repayment reminders
    if (currentHour === 9 && currentMinute >= 0 && currentMinute <= 4) {
      await executeJob('repayment_reminders').catch((e) => console.error('Worker repayment reminders error:', e.message));
    }
  } catch (err: any) {
    console.error('[Worker Error]:', err.message);
  }
  console.log(`[${new Date().toISOString()}] === USMS Background Worker Cycle Finished ===\n`);
}

async function main() {
  console.log('🚀 Umoja SACCO Background Worker Daemon Initialized.');
  console.log('Running immediate initial cycle...');
  await runWorkerCycle();

  const isDaemon = process.argv.includes('--daemon');
  if (isDaemon) {
    console.log('Worker is running in DAEMON mode (polling every 60 seconds). Press Ctrl+C to stop.');
    setInterval(async () => {
      await runWorkerCycle();
    }, 60000);
  } else {
    await prisma.$disconnect();
    process.exit(0);
  }
}

if (require.main === module) {
  main();
}
