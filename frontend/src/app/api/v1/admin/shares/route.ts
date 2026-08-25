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

    const [shareholdings, shareTransactions, shareSettings, dividendPeriods] = await Promise.all([
      prisma.memberShareholdings.findMany().catch(() => []),
      prisma.shareTransactions.findMany({ orderBy: { created_at: 'desc' }, take: 50 }).catch(() => []),
      prisma.shareSettings.findFirst().catch(() => null),
      prisma.dividendPeriods.findMany({ orderBy: { fiscal_year: 'desc' } }).catch(() => []),
    ]);

    const totalUnits = shareholdings.reduce((sum, s) => sum + Number(s.units_owned || 0), 0);
    const totalCapital = shareholdings.reduce((sum, s) => sum + Number(s.total_amount_paid || 0), 0);

    return apiSuccess({
      shareholdings,
      recent_transactions: shareTransactions,
      settings: shareSettings || {
        initial_unit_price: 20,
        total_authorized_units: 1000000,
        par_value: 20,
      },
      dividend_periods: dividendPeriods,
      summary: {
        total_shareholders: shareholdings.length,
        total_units_issued: totalUnits,
        total_share_capital: totalCapital,
        current_unit_price: 20,
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch share capital records', 500);
  }
}
