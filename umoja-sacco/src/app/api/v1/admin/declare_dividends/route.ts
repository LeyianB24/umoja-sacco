import { NextRequest } from 'next/server';
import { executeJob } from '@/jobs/runner';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const period = Number(body.period || body.fiscal_year || new Date().getFullYear() - 1);
    const dryRun = Boolean(body.dry_run || body.dryRun);

    const result = await executeJob('dividend_distribution', {
      period,
      dryRun,
    });

    return apiSuccess(result, `Dividend declaration run completed for FY ${period}.`);
  } catch (err: any) {
    return apiError(err.message || 'Failed to execute dividend distribution', 500);
  }
}
