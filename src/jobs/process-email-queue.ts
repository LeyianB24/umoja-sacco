import { prisma } from '../lib/prisma';
import { sendEmail } from '../lib/email';
import { JobArgs, JobResult } from './types';

export async function runProcessEmailQueue(args: JobArgs = {}): Promise<JobResult> {
  const startTime = Date.now();
  const batchSize = Number(args.batchSize || 20);

  console.log(`[process_email_queue] Processing email queue (batch size: ${batchSize})...`);

  const pendingEmails = await prisma.emailQueue.findMany({
    where: {
      status: 'pending',
      attempts: { lt: 3 },
    },
    orderBy: [
      { priority: 'desc' },
      { created_at: 'asc' },
    ],
    take: batchSize,
  });

  if (pendingEmails.length === 0) {
    const durationMs = Date.now() - startTime;
    console.log('[process_email_queue] No pending emails in queue.');
    return {
      jobName: 'process_email_queue',
      success: true,
      recordsProcessed: 0,
      message: 'No pending emails to process.',
      durationMs,
    };
  }

  console.log(`[process_email_queue] Found ${pendingEmails.length} pending email(s) to dispatch.`);

  let sentCount = 0;
  let failCount = 0;

  for (const item of pendingEmails) {
    try {
      await sendEmail({
        to: item.recipient_email,
        subject: item.subject,
        html: item.body,
      });

      await prisma.emailQueue.update({
        where: { queue_id: item.queue_id },
        data: {
          status: 'sent',
          sent_at: new Date(),
          attempts: { increment: 1 },
        },
      });

      sentCount++;
      console.log(`  [SENT] Email #${item.queue_id} to ${item.recipient_email} ("${item.subject}")`);
    } catch (err: any) {
      failCount++;
      const attempts = (item.attempts || 0) + 1;
      const isFailed = attempts >= 3;

      await prisma.emailQueue.update({
        where: { queue_id: item.queue_id },
        data: {
          attempts,
          status: isFailed ? 'failed' : 'pending',
          last_error: err.message || 'SMTP dispatch error',
        },
      });

      console.error(`  [FAILED] Email #${item.queue_id} to ${item.recipient_email}:`, err.message);
    }
  }

  const durationMs = Date.now() - startTime;
  const message = `Email Queue Batch Complete: ${sentCount} sent, ${failCount} failed out of ${pendingEmails.length}. Duration: ${durationMs}ms`;
  console.log(`[process_email_queue] ${message}`);

  return {
    jobName: 'process_email_queue',
    success: failCount === 0,
    recordsProcessed: sentCount,
    message,
    durationMs,
    details: { sent: sentCount, failed: failCount, total: pendingEmails.length },
  };
}
