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

    const [membersCount, savingsAgg, loansAgg, finesAgg, vehiclesCount] = await Promise.all([
      prisma.members.count().catch(() => 0),
      prisma.savings.aggregate({ _sum: { amount: true } }).catch(() => null),
      prisma.loans.aggregate({ _sum: { amount: true } }).catch(() => null),
      prisma.fines.aggregate({ _sum: { amount: true } }).catch(() => null),
      prisma.vehicles.count().catch(() => 0),
    ]);

    return apiSuccess({
      metrics: {
        total_members: membersCount,
        total_savings: Number(savingsAgg?._sum?.amount || 0),
        total_loans_disbursed: Number(loansAgg?._sum?.amount || 0),
        total_fines_collected: Number(finesAgg?._sum?.amount || 0),
        vehicles_under_management: vehiclesCount,
      },
      available_reports: [
        { id: 'trial_balance', name: 'General Ledger Trial Balance', format: 'PDF, Excel' },
        { id: 'member_savings', name: 'Member Savings & Share Register', format: 'Excel' },
        { id: 'loan_portfolio', name: 'Loan Portfolio Aging & Default Risk', format: 'PDF, Excel' },
        { id: 'statutory_tax', name: 'Statutory Withholding Tax Returns (5% WHT)', format: 'Excel' },
        { id: 'payroll_summary', name: 'Monthly Payroll & Statutory Deductions (NSSF, SHA, PAYE)', format: 'PDF' },
      ],
      generated_at: new Date().toISOString(),
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch financial reports summary', 500);
  }
}
