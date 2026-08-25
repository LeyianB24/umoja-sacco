import { prisma } from '../lib/prisma';
import { JobArgs, JobResult } from './types';

export async function runDailyFines(args: JobArgs = {}): Promise<JobResult> {
  const startTime = Date.now();
  const dryRun = Boolean(args.dryRun);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  console.log(`[daily_fines] Starting daily fines job... ${dryRun ? '[DRY-RUN]' : ''}`);

  // 1. Fetch overdue active/disbursed loans
  const overdueLoans = await prisma.loans.findMany({
    where: {
      status: { in: ['active', 'disbursed'] },
      next_repayment_date: { lt: today },
    },
  });

  console.log(`[daily_fines] Found ${overdueLoans.length} overdue loan candidate(s).`);

  let appliedCount = 0;

  for (const loan of overdueLoans) {
    // Check if fine already applied today
    const existingFine = await prisma.fines.findFirst({
      where: {
        loan_id: loan.loan_id,
        date_applied: today,
      },
    });

    if (existingFine) {
      console.log(`[daily_fines] Fine already applied today for Loan #${loan.loan_id}. Skipping.`);
      continue;
    }

    // 0.05% fine on principal, minimum KES 1.00
    const principal = Number(loan.amount || 0);
    let fineAmount = Math.round(principal * 0.0005 * 100) / 100;
    if (fineAmount < 1.0) fineAmount = 1.0;

    if (dryRun) {
      console.log(`[daily_fines][DRY-RUN] Would apply KES ${fineAmount} fine to Loan #${loan.loan_id} (Member #${loan.member_id}).`);
      appliedCount++;
      continue;
    }

    try {
      // 1. Record fine in fines table
      await prisma.fines.create({
        data: {
          loan_id: loan.loan_id,
          amount: fineAmount,
          date_applied: today,
        },
      });

      // 2. Increment loan balance
      await prisma.loans.update({
        where: { loan_id: loan.loan_id },
        data: {
          current_balance: {
            increment: fineAmount,
          },
        },
      });

      // 3. Record ledger transaction
      const refNo = `FINE-${loan.loan_id}-${today.toISOString().slice(0, 10).replace(/-/g, '')}`;
      await prisma.transactions.create({
        data: {
          member_id: loan.member_id,
          amount: fineAmount,
          transaction_type: 'fine',
          type: 'debit',
          category: 'Fines',
          description: `Daily late payment penalty for Loan #${loan.loan_id}`,
          reference_no: refNo,
          payment_channel: 'system',
          transaction_date: new Date(),
        },
      });

      // 4. Queue notification email if member email exists
      const member = await prisma.members.findUnique({
        where: { member_id: loan.member_id },
      });

      if (member && member.email) {
        const newBalance = Number(loan.current_balance || 0) + fineAmount;
        await prisma.emailQueue.create({
          data: {
            recipient_email: member.email,
            recipient_name: member.full_name,
            subject: `Late Payment Penalty Applied - Loan #${loan.loan_id}`,
            body: `<p>Dear ${member.full_name},</p>
<p>A daily late payment penalty of <b>KES ${fineAmount.toFixed(2)}</b> has been applied to your loan account (#${loan.loan_id}) because your scheduled repayment is overdue.</p>
<p>Your current outstanding balance is <b>KES ${newBalance.toFixed(2)}</b>.</p>
<p>Please make your payment promptly via the Member Portal or M-Pesa to avoid further penalties.</p>
<p>Thank you for choosing Umoja SACCO.</p>`,
            status: 'pending',
          },
        });
      }

      appliedCount++;
      console.log(`[daily_fines] Applied KES ${fineAmount} fine to Loan #${loan.loan_id}`);
    } catch (err: any) {
      console.error(`[daily_fines] Failed to apply fine to Loan #${loan.loan_id}:`, err.message);
    }
  }

  const durationMs = Date.now() - startTime;
  const message = `Daily fines processed: ${appliedCount} overdue loan(s) fined. Duration: ${durationMs}ms`;
  console.log(`[daily_fines] ${message}`);

  return {
    jobName: 'daily_fines',
    success: true,
    recordsProcessed: appliedCount,
    message,
    durationMs,
  };
}
