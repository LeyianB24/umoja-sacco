import { prisma } from '../lib/prisma';
import { JobArgs, JobResult } from './types';

export async function runDividendDistribution(args: JobArgs = {}): Promise<JobResult> {
  const startTime = Date.now();
  const dryRun = Boolean(args.dryRun);
  const periodYear = Number(args.period || new Date().getFullYear() - 1);

  console.log(`[dividend_distribution] Calculating dividends for Fiscal Year ${periodYear}... ${dryRun ? '[DRY-RUN]' : ''}`);

  // 1. Check or create dividend period
  let period = await prisma.dividendPeriods.findFirst({
    where: { fiscal_year: periodYear },
  });

  let totalPool = Number(period?.total_pool || 0);

  if (totalPool <= 0) {
    // Estimate surplus from savings revenue if pool not pre-configured
    const savingsRevenueAgg = await prisma.savings.aggregate({
      _sum: { amount: true },
    });
    const totalSavings = Number(savingsRevenueAgg._sum.amount || 1000000);
    // Standard dividend rate: 12% on savings
    totalPool = Math.round(totalSavings * 0.12 * 100) / 100;
  }

  console.log(`[dividend_distribution] Dividend Pool for FY ${periodYear}: KES ${totalPool.toLocaleString()}`);

  // 2. Fetch all active members and aggregate their savings
  const activeMembers = await prisma.members.findMany({
    where: { status: 'active' },
  });

  // Calculate total system savings across all members
  const memberSavingsMap: { [memberId: number]: number } = {};
  let aggregateSavings = 0;

  for (const m of activeMembers) {
    const agg = await prisma.savings.aggregate({
      where: { member_id: m.member_id },
      _sum: { amount: true },
    });
    const bal = Number(agg._sum.amount || 0);
    memberSavingsMap[m.member_id] = bal;
    aggregateSavings += bal;
  }

  if (aggregateSavings <= 0) {
    aggregateSavings = activeMembers.length * 10000; // Fallback baseline
  }

  let distributedCount = 0;
  let totalDistributed = 0;

  if (!period && !dryRun) {
    period = await prisma.dividendPeriods.create({
      data: {
        fiscal_year: periodYear,
        total_pool: totalPool,
        rate_percentage: 12.0,
        status: 'approved',
      },
    });
  }

  for (const m of activeMembers) {
    const savings = memberSavingsMap[m.member_id] || 0;
    if (savings <= 0) continue;

    const shareRatio = savings / aggregateSavings;
    const grossDividend = Math.round(totalPool * shareRatio * 100) / 100;
    if (grossDividend <= 0) continue;

    // 15% withholding tax by default under cooperative dividend regulations (or custom arg)
    const whtRate = typeof args.whtRate === 'number' ? args.whtRate : 0.15;
    const withholdingTax = Math.round(grossDividend * whtRate * 100) / 100;
    const netDividend = Math.round((grossDividend - withholdingTax) * 100) / 100;

    if (dryRun) {
      console.log(`[dividend_distribution][DRY-RUN] ${m.full_name}: Gross KES ${grossDividend.toFixed(2)}, Tax (${whtRate * 100}%) KES ${withholdingTax.toFixed(2)}, Net KES ${netDividend.toFixed(2)}`);
      distributedCount++;
      totalDistributed += netDividend;
      continue;
    }

    try {
      if (period) {
        // Record payout
        await prisma.dividendPayouts.create({
          data: {
            period_id: period.period_id,
            member_id: m.member_id,
            gross_amount: grossDividend,
            wht_tax: withholdingTax,
            net_amount: netDividend,
            status: 'paid',
            paid_at: new Date(),
          },
        });
      }

      // Credit member's savings account with net dividend
      const refNo = `DIV-${periodYear}-${m.member_id}`;
      await prisma.savings.create({
        data: {
          member_id: m.member_id,
          amount: netDividend,
          transaction_type: 'deposit',
          description: `Annual Dividend Distribution for FY ${periodYear} (Net of 5% WHT)`,
          reference_no: refNo,
        },
      });

      // Record transaction
      await prisma.transactions.create({
        data: {
          member_id: m.member_id,
          amount: netDividend,
          transaction_type: 'dividend',
          type: 'credit',
          category: 'Dividends',
          description: `Annual Dividend for FY ${periodYear}`,
          reference_no: refNo,
          payment_channel: 'system',
          transaction_date: new Date(),
        },
      });

      // Queue email notification
      if (m.email) {
        await prisma.emailQueue.create({
          data: {
            recipient_email: m.email,
            recipient_name: m.full_name,
            subject: `Annual Dividend Credited - FY ${periodYear}`,
            body: `<p>Dear ${m.full_name},</p>
<p>We are pleased to announce that your annual dividend for Fiscal Year <b>${periodYear}</b> has been approved and credited to your savings account.</p>
<p><b>Gross Dividend:</b> KES ${grossDividend.toFixed(2)}<br/>
<b>Withholding Tax (5%):</b> KES ${withholdingTax.toFixed(2)}<br/>
<b>Net Credited Amount:</b> <b>KES ${netDividend.toFixed(2)}</b></p>
<p>You can view your updated savings balance and dividend statement directly on the Member Portal.</p>
<p>Thank you for your continued commitment and patronage.</p>
<p>Warm regards,<br/><b>Umoja SACCO Management Board</b></p>`,
            status: 'pending',
          },
        });
      }

      distributedCount++;
      totalDistributed += netDividend;
    } catch (err: any) {
      console.error(`[dividend_distribution] Failed to credit dividend for Member #${m.member_id}:`, err.message);
    }
  }

  const durationMs = Date.now() - startTime;
  const message = `Dividend Distribution Complete for FY ${periodYear}: Distributed KES ${totalDistributed.toFixed(2)} across ${distributedCount} active member(s). Duration: ${durationMs}ms`;
  console.log(`[dividend_distribution] ${message}`);

  return {
    jobName: 'dividend_distribution',
    success: true,
    recordsProcessed: distributedCount,
    message,
    durationMs,
    details: {
      periodYear,
      totalPool,
      totalDistributed,
      membersRewarded: distributedCount,
    },
  };
}
