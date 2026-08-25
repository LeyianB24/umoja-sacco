import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

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
    const loanType = body.loan_type || 'emergency';
    const duration = Number(body.duration || 12);
    const interestRate = Number(body.interest_rate || 12.0);

    if (!amount || amount <= 0) {
      return apiError('Please enter a valid loan amount.', 422);
    }

    const newLoan = await prisma.loans.create({
      data: {
        member_id: session.userId,
        amount: amount,
        loan_type: loanType,
        interest_rate: interestRate,
        repayment_period_months: duration,
        status: 'pending',
        application_date: new Date(),
        created_at: new Date(),
      },
    });

    return apiSuccess(newLoan, 'Loan application submitted successfully and queued for review.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to submit loan application', 500);
  }
}
