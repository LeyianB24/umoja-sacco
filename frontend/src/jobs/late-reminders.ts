import { prisma } from '../lib/prisma';
import { JobArgs, JobResult } from './types';

export async function runLateReminders(args: JobArgs = {}): Promise<JobResult> {
  const startTime = Date.now();
  const dryRun = Boolean(args.dryRun);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  console.log(`[late_reminders] Scanning overdue loans... ${dryRun ? '[DRY-RUN]' : ''}`);

  const overdueLoans = await prisma.loans.findMany({
    where: {
      status: { in: ['active', 'disbursed'] },
      next_repayment_date: { lt: today },
    },
  });

  console.log(`[late_reminders] Found ${overdueLoans.length} overdue loan(s).`);

  let sentCount = 0;

  for (const loan of overdueLoans) {
    const member = await prisma.members.findUnique({
      where: { member_id: loan.member_id },
    });

    if (!member || !member.email) continue;

    const dueDateStr = loan.next_repayment_date
      ? new Date(loan.next_repayment_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
      : 'Past Due';

    const currentBalance = Number(loan.current_balance || loan.amount || 0);

    if (dryRun) {
      console.log(`[late_reminders][DRY-RUN] Would send urgent overdue notice to ${member.full_name} (${member.email}) for Loan #${loan.loan_id}.`);
      sentCount++;
      continue;
    }

    try {
      await prisma.emailQueue.create({
        data: {
          recipient_email: member.email,
          recipient_name: member.full_name,
          subject: `URGENT: Overdue Loan Repayment Notice - Loan #${loan.loan_id}`,
          body: `<p>Dear <b>${member.full_name}</b>,</p>
<p>This is a formal urgent notification regarding your outstanding loan with Umoja SACCO: <b>Loan #${loan.loan_id}</b>.</p>
<p>Our records indicate that your repayment was due on <b>${dueDateStr}</b> and remains unpaid.</p>
<p><b>Current Outstanding Balance:</b> KES ${currentBalance.toFixed(2)}</p>
<p>Please clear your overdue arrears immediately to halt accumulating daily penalties and maintain your credit score with the SACCO.</p>
<p>Payments can be made directly through the Member Portal or via M-Pesa Paybill.</p>
<p>If you have already settled this payment in the past 24 hours, please contact member support.</p>
<p>Sincerely,<br/><b>Umoja SACCO Credit & Compliance Desk</b></p>`,
          status: 'pending',
        },
      });

      sentCount++;
      console.log(`[late_reminders] Queued urgent overdue notice for ${member.full_name} - Loan #${loan.loan_id}`);
    } catch (err: any) {
      console.error(`[late_reminders] Failed to queue overdue notice for Loan #${loan.loan_id}:`, err.message);
    }
  }

  const durationMs = Date.now() - startTime;
  const message = `Late reminders processed: ${sentCount} overdue notice(s) queued.`;
  console.log(`[late_reminders] ${message}`);

  return {
    jobName: 'late_reminders',
    success: true,
    recordsProcessed: sentCount,
    message,
    durationMs,
  };
}
