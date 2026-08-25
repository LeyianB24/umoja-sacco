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

    const [employees, salaryGrades] = await Promise.all([
      prisma.employees.findMany({ orderBy: { employee_id: 'asc' } }).catch(() => []),
      prisma.salaryGrades.findMany().catch(() => []),
    ]);

    return apiSuccess({
      employees,
      salary_grades: salaryGrades,
      total_employees: employees.length,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch employees list', 500);
  }
}
