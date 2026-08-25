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

    const savingsHistory = await prisma.savings.findMany({
      where: { member_id: memberId },
      orderBy: { created_at: 'desc' },
    }).catch(() => []);

    return apiSuccess({
      savings_balance: balances.savings,
      interest_earned: 0,
      monthly_target: 5000,
      history: savingsHistory,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch savings data', 500);
  }
}
