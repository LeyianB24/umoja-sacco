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
    const cases = await prisma.welfareCases.findMany({
      orderBy: { created_at: 'desc' },
      take: 20,
    }).catch(() => []);

    const myDonations = await prisma.welfareDonations.findMany({
      where: { member_id: memberId },
      orderBy: { created_at: 'desc' },
    }).catch(() => []);

    const totalDonated = myDonations.reduce((sum, d) => sum + Number(d.amount || 0), 0);

    return apiSuccess({
      welfare_fund_balance: 1540000,
      my_contributions: totalDonated,
      active_cases: cases.filter((c) => c.status === 'active'),
      cases,
      donations: myDonations,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch welfare data', 500);
  }
}
