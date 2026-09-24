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
    const memberId = Number(searchParams.get('id') || searchParams.get('member_id'));

    if (!memberId) {
      return apiError('Member ID is required.', 422);
    }

    const member = await prisma.members.findUnique({
      where: { member_id: memberId },
    });

    if (!member) {
      return apiError('Member not found.', 404);
    }

    const [balances, loans, transactions, documents] = await Promise.all([
      getMemberBalances(memberId),
      prisma.loans.findMany({ where: { member_id: memberId }, orderBy: { created_at: 'desc' } }).catch(() => []),
      prisma.transactions.findMany({ where: { member_id: memberId }, orderBy: { transaction_date: 'desc' }, take: 20 }).catch(() => []),
      prisma.memberDocuments.findMany({ where: { member_id: memberId } }).catch(() => []),
    ]);

    return apiSuccess({
      member,
      balances,
      loans,
      transactions,
      documents,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch member details', 500);
  }
}
