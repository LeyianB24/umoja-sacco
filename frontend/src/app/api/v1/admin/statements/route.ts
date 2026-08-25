import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { getMemberBalances } from '@/lib/financial';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const memberId = Number(searchParams.get('member_id'));

    if (memberId) {
      const [member, balances, transactions] = await Promise.all([
        prisma.members.findUnique({ where: { member_id: memberId } }),
        getMemberBalances(memberId),
        prisma.transactions.findMany({
          where: { member_id: memberId },
          orderBy: { transaction_date: 'desc' },
        }),
      ]);

      return apiSuccess({
        member,
        balances,
        statement_period: 'Full Lifetime',
        transactions,
        generated_at: new Date().toISOString(),
      });
    }

    // Return general transaction statement list
    const transactions = await prisma.transactions.findMany({
      orderBy: { transaction_date: 'desc' },
      take: 100,
    });

    return apiSuccess({
      transactions,
      generated_at: new Date().toISOString(),
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to generate financial statement', 500);
  }
}
