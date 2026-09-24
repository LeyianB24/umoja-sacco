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
      pendingLoans,
      savingsAgg,
      loansAgg,
    ] = await Promise.all([
      prisma.members.count().catch(() => 0),
      prisma.members.count({ where: { status: 'active' } }).catch(() => 0),
      prisma.loans.count().catch(() => 0),
      prisma.loans.count({ where: { status: 'pending' } }).catch(() => 0),
      prisma.savings.aggregate({ _sum: { amount: true } }).catch(() => null),
      prisma.loans.aggregate({
        where: { status: { in: ['approved', 'disbursed', 'active', 'repaying'] } },
        _sum: { amount: true },
      }).catch(() => null),
    ]);

    return apiSuccess({
      total_members: totalMembers,
      active_members: activeMembers,
      total_loans: loansCount,
      pending_loans: pendingLoans,
      total_savings: Number(savingsAgg?._sum?.amount || 0),
      total_disbursed: Number(loansAgg?._sum?.amount || 0),
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch admin statistics', 500);
  }
}
