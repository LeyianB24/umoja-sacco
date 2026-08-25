import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const [
      totalMembers,
      activeMembers,
      loansCount,
      transactionsCount,
      recentTransactions,
      pendingLoans,
    ] = await Promise.all([
      prisma.members.count().catch(() => 0),
      prisma.members.count({ where: { status: 'active' } }).catch(() => 0),
      prisma.loans.count().catch(() => 0),
      prisma.transactions.count().catch(() => 0),
      prisma.transactions.findMany({
        orderBy: { transaction_date: 'desc' },
        take: 8,
      }).catch(() => []),
      prisma.loans.findMany({
        where: { status: 'pending' },
        orderBy: { application_date: 'desc' },
        take: 5,
      }).catch(() => []),
    ]);

    const savingsAgg = await prisma.savings.aggregate({
      _sum: { amount: true },
    }).catch(() => null);

    const loansAgg = await prisma.loans.aggregate({
      where: { status: { in: ['approved', 'disbursed', 'active', 'repaying'] } },
      _sum: { amount: true },
    }).catch(() => null);

    return apiSuccess({
      metrics: {
        total_members: totalMembers,
        active_members: activeMembers,
        total_savings: Number(savingsAgg?._sum?.amount || 0),
        total_loans_disbursed: Number(loansAgg?._sum?.amount || 0),
        pending_loan_applications: pendingLoans.length,
        total_transactions: transactionsCount,
      },
      recent_transactions: recentTransactions,
      pending_loans: pendingLoans,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch admin dashboard metrics', 500);
  }
}
