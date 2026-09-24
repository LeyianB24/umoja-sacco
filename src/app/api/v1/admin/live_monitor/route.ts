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

    const [recentTransactions, pendingLoans, activeMembersCount, alerts] = await Promise.all([
      prisma.transactions.findMany({
        orderBy: { transaction_date: 'desc' },
        take: 15,
      }).catch(() => []),
      prisma.loans.findMany({
        where: { status: 'pending' },
        orderBy: { application_date: 'desc' },
        take: 10,
      }).catch(() => []),
      prisma.members.count({ where: { status: 'active' } }).catch(() => 0),
      prisma.transactionAlerts.findMany({
        where: { acknowledged: false },
        orderBy: { created_at: 'desc' },
        take: 10,
      }).catch(() => []),
    ]);

    return apiSuccess({
      live_transactions: recentTransactions,
      pending_approvals: pendingLoans,
      active_members: activeMembersCount,
      system_alerts: alerts,
      server_time: new Date().toISOString(),
      database_status: 'online',
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch live monitoring feed', 500);
  }
}
