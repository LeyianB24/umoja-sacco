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

    // Active loans
    const loans = await prisma.loans.findMany({
      where: {
        member_id: memberId,
        status: { in: ['approved', 'disbursed', 'active', 'repaying'] },
      },
    }).catch(() => []);

    // Recent transactions
    const transactions = await prisma.transactions.findMany({
      where: { member_id: memberId },
      orderBy: { transaction_date: 'desc' },
      take: 10,
    }).catch(() => []);

    return apiSuccess({
      member: {
        id: member.member_id,
        name: member.full_name,
        reg_no: member.member_reg_no,
        email: member.email,
        phone: member.phone,
        status: member.status,
        kyc_status: member.kyc_status,
      },
      balances,
      recent_transactions: transactions,
      active_loans: loans,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch member dashboard data', 500);
  }
}
