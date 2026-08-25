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

    const [payrollRuns, employees, salaryGrades] = await Promise.all([
      prisma.payrollRuns.findMany({ orderBy: { created_at: 'desc' } }).catch(() => []),
      prisma.employees.findMany({ where: { status: 'active' } }).catch(() => []),
      prisma.salaryGrades.findMany().catch(() => []),
    ]);

    const totalSalaryCommitment = employees.reduce((sum, e) => sum + Number(e.salary || 0), 0);

    return apiSuccess({
      payroll_runs: payrollRuns,
      active_employees: employees.length,
      total_salary_commitment: totalSalaryCommitment,
      salary_grades: salaryGrades,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch payroll records', 500);
  }
}
