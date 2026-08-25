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

    const [vehicleExpenses, investmentExpenses] = await Promise.all([
      prisma.vehicleExpenses.findMany({ orderBy: { expense_date: 'desc' }, take: 50 }).catch(() => []),
      prisma.investmentExpenses.findMany({ orderBy: { expense_date: 'desc' }, take: 50 }).catch(() => []),
    ]);

    const totalVehicleExpenses = vehicleExpenses.reduce((sum, e) => sum + Number(e.amount || 0), 0);
    const totalInvestmentExpenses = investmentExpenses.reduce((sum, e) => sum + Number(e.amount || 0), 0);

    return apiSuccess({
      vehicle_expenses: vehicleExpenses,
      investment_expenses: investmentExpenses,
      summary: {
        total_vehicle_expenses: totalVehicleExpenses,
        total_investment_expenses: totalInvestmentExpenses,
        total_operational_expenses: totalVehicleExpenses + totalInvestmentExpenses,
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch expense records', 500);
  }
}
