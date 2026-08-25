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

    const accounts = await prisma.ledgerAccounts.findMany({
      orderBy: { account_id: 'asc' },
    });

    let totalDebits = 0;
    let totalCredits = 0;

    const rows = await Promise.all(
      accounts.map(async (acc) => {
        const agg = await prisma.ledgerEntries.aggregate({
          where: { account_id: acc.account_id },
          _sum: { debit: true, credit: true },
        });

        const debit = Number(agg._sum.debit || 0);
        const credit = Number(agg._sum.credit || 0);
        totalDebits += debit;
        totalCredits += credit;

        return {
          account_id: acc.account_id,
          account_name: acc.account_name,
          account_type: acc.account_type,
          category: acc.category,
          debit,
          credit,
          balance: Number(acc.current_balance || 0),
        };
      })
    );

    return apiSuccess({
      accounts: rows,
      totals: {
        total_debits: totalDebits,
        total_credits: totalCredits,
        difference: Math.round((totalDebits - totalCredits) * 100) / 100,
        is_balanced: Math.abs(totalDebits - totalCredits) <= 0.01,
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to generate trial balance', 500);
  }
}
