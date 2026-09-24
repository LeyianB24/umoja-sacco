import { prisma } from '../lib/prisma';
import { JobArgs, JobResult } from './types';

export async function runRepaymentReminders(args: JobArgs = {}): Promise<JobResult> {
  const startTime = Date.now();
  const dryRun = Boolean(args.dryRun);

  // Target date: exactly 3 days from today
  const targetDate = new Date();
  targetDate.setDate(targetDate.getDate() + 3);
  targetDate.setHours(0, 0, 0, 0);

  const nextDay = new Date(targetDate);
  nextDay.setDate(nextDay.getDate() + 1);

  console.log(`[repayment_reminders] Scanning loans due on ${targetDate.toISOString().slice(0, 10)}... ${dryRun ? '[DRY-RUN]' : ''}`);

  const upcomingLoans = await prisma.loans.findMany({
    where: {
      status: { in: ['active', 'disbursed'] },
      next_repayment_date: {
        gte: targetDate,
        lt: nextDay,
      },
    },
  });

  console.log(`[repayment_reminders] Found ${upcomingLoans.length} loan(s) due in 3 days.`);

  let sentCount = 0;

  for (const loan of upcomingLoans) {
    const member = await prisma.members.findUnique({
      where: { member_id: loan.member_id },
    });

    if (!member || !member.email) continue;

    const dueDateStr = loan.next_repayment_date
      ? new Date(loan.next_repayment_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
      : targetDate.toDateString();

    const currentBalance = Number(loan.current_balance || loan.amount || 0);

    if (dryRun) {
      console.log(`[repayment_reminders][DRY-RUN] Would send reminder to ${member.full_name} (${member.email}) for Loan #${loan.loan_id}.`);
      sentCount++;
      continue;
    }

    try {
      await prisma.emailQueue.create({
        data: {
          recipient_email: member.email,
          recipient_name: member.full_name,
          subject: `Upcoming Repayment Reminder - Loan #${loan.loan_id}`,
          body: `<p>Dear ${member.full_name},</p>
<p>This is a friendly reminder that your upcoming loan repayment for <b>Loan #${loan.loan_id}</b> is scheduled for <b>${dueDateStr}</b>.</p>
<p><b>Current Outstanding Balance:</b> KES ${currentBalance.toFixed(2)}</p>
<p>Please ensure you make your repayment on or before the due date to keep your loan account in good standing and avoid late payment penalties.</p>
<p>You can pay conveniently via M-Pesa Paybill or directly in the Member Portal.</p>
<p>Thank you for partnering with Umoja SACCO.</p>`,
          status: 'pending',
        },
      });

      sentCount++;
      console.log(`[repayment_reminders] Queued reminder for Member #${member.member_id} (${member.email}) - Loan #${loan.loan_id}`);
    } catch (err: any) {
      console.error(`[repayment_reminders] Failed to queue reminder for Loan #${loan.loan_id}:`, err.message);
    }
  }

  const durationMs = Date.now() - startTime;
  const message = `Repayment reminders processed: ${sentCount} reminder(s) queued for loans due in 3 days.`;
  console.log(`[repayment_reminders] ${message}`);

  return {
    jobName: 'repayment_reminders',
    success: true,
    recordsProcessed: sentCount,
    message,
    durationMs,
  };
}
