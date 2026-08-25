import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { getMemberBalances } from '@/lib/financial';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const memberId = session.userId;
    const member = await prisma.members.findUnique({
      where: { member_id: memberId },
    });

    if (!member) {
      return apiError('Member not found', 404);
    }

    const balances = await getMemberBalances(memberId);

    const shareTransactions = await prisma.shareTransactions.findMany({
      where: { member_id: memberId },
      orderBy: { created_at: 'desc' },
    }).catch(() => []);

    return apiSuccess({
      total_shares: balances.shares,
      share_value: 20, // KES 20 per share standard
      total_valuation: balances.shares_valuation,
      annual_dividend_rate: 14.2,
      transactions: shareTransactions,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch shares data', 500);
  }
}
