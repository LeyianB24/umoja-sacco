import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
import { createNotification } from '@/lib/notifications';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const memberId = session.userId;
    const loans = await prisma.loans.findMany({
      where: { member_id: memberId },
      orderBy: { created_at: 'desc' },
    }).catch(() => []);

    return apiSuccess({
      loans,
      total_loans: loans.length,
      active_loans: loans.filter((l) => ['approved', 'disbursed', 'active', 'repaying'].includes(l.status || '')),
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch member loans', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const amount = Number(body.amount);
    const rawLoanType = String(body.loan_type || 'emergency').toLowerCase().trim();
    const duration = Number(body.duration || 12);

    // Official SASRA Compliant Sacco Loan Rate Schedule (Annual Interest)
    const LOAN_MATRIX: Record<string, { rate: number; minAmt: number; maxAmt: number; minMos: number; maxMos: number }> = {
      express: { rate: 8.0, minAmt: 5000, maxAmt: 100000, minMos: 1, maxMos: 6 },
      development: { rate: 10.0, minAmt: 20000, maxAmt: 2000000, minMos: 3, maxMos: 36 },
      dev: { rate: 10.0, minAmt: 20000, maxAmt: 2000000, minMos: 3, maxMos: 36 },
      asset: { rate: 12.0, minAmt: 100000, maxAmt: 4000000, minMos: 6, maxMos: 48 },
      emergency: { rate: 6.0, minAmt: 5000, maxAmt: 80000, minMos: 1, maxMos: 4 },
    };

    const config = LOAN_MATRIX[rawLoanType] || { rate: 12.0, minAmt: 5000, maxAmt: 1000000, minMos: 1, maxMos: 24 };

    if (!amount || isNaN(amount) || amount < config.minAmt || amount > config.maxAmt) {
      return apiError(`Invalid amount for ${rawLoanType} loan. Allowed limits: KES ${config.minAmt.toLocaleString()} to KES ${config.maxAmt.toLocaleString()}.`, 422);
    }

    if (duration < config.minMos || duration > config.maxMos) {
      return apiError(`Invalid tenure for ${rawLoanType} loan. Allowed duration: ${config.minMos} to ${config.maxMos} months.`, 422);
    }

    // Authoritative interest rate computed strictly on server
    const interestRate = config.rate;
    const totalInterest = amount * (interestRate / 100) * (duration / 12);
    const totalPayable = Math.round((amount + totalInterest) * 100) / 100;
    const loanType = rawLoanType;

    const newLoan = await prisma.loans.create({
      data: {
        member_id: session.userId,
        amount: amount,
        loan_type: loanType,
        interest_rate: interestRate,
        duration_months: duration,
        total_payable: totalPayable,
        current_balance: totalPayable,
        status: 'pending',
        application_date: new Date(),
        created_at: new Date(),
      },
    });

    // Send confirmation notification to member
    await createNotification({
      memberId: session.userId,
      title: 'Loan Application Submitted',
      message: `Your loan application #${newLoan.loan_id} for KES ${amount.toLocaleString()} has been received and is under review by the credit committee.`,
      metadata: { loanId: newLoan.loan_id, amount, loanType },
    });

    return apiSuccess(newLoan, 'Loan application submitted successfully and queued for review.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to submit loan application', 500);
  }
}

