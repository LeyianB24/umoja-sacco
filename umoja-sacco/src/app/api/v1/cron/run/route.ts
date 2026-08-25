import { NextRequest } from 'next/server';
import { executeJob, REGISTERED_JOBS } from '@/jobs/runner';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const jobName = searchParams.get('job') || '';
    const dryRun = searchParams.get('dry_run') === 'true' || searchParams.get('dry-run') === 'true';
    const fix = searchParams.get('fix') === 'true';
    const batchSize = parseInt(searchParams.get('batch') || '20', 10);
    const period = parseInt(searchParams.get('period') || '0', 10);

    // Security check: Either valid admin session OR matching CRON_SECRET authorization header
    const cronSecret = process.env.CRON_SECRET || 'umoja_cron_secret_key_2026';
    const authHeader = request.headers.get('authorization') || '';
    const isSecretAuthorized = authHeader === `Bearer ${cronSecret}` || request.headers.get('x-cron-secret') === cronSecret;

    const session = await getAuthSession(request);
    const isAdmin = session && session.userType === 'admin';

    if (!isSecretAuthorized && !isAdmin) {
      return apiError('Unauthorized. Admin session or valid CRON_SECRET required.', 401);
    }

    if (!jobName) {
      return apiSuccess({
        available_jobs: Object.entries(REGISTERED_JOBS).map(([key, def]) => ({
          name: key,
          description: def.description,
          schedule: def.schedule,
        })),
      });
    }

    if (!REGISTERED_JOBS[jobName]) {
      return apiError(`Unknown job "${jobName}". Query /api/v1/cron/run for available jobs.`, 404);
    }

    const result = await executeJob(jobName, {
      dryRun,
      fix,
      batchSize,
      period: period || undefined,
    });

    return apiSuccess(result, `Job "${jobName}" executed successfully.`);
  } catch (err: any) {
    return apiError(err.message || 'Job execution failed', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const jobName = body.job || body.jobName || '';
    const dryRun = Boolean(body.dryRun || body.dry_run);
    const fix = Boolean(body.fix);
    const batchSize = Number(body.batchSize || body.batch || 20);
    const period = Number(body.period || 0);

    // Security check
    const cronSecret = process.env.CRON_SECRET || 'umoja_cron_secret_key_2026';
    const authHeader = request.headers.get('authorization') || '';
    const isSecretAuthorized = authHeader === `Bearer ${cronSecret}` || request.headers.get('x-cron-secret') === cronSecret;

    const session = await getAuthSession(request);
    const isAdmin = session && session.userType === 'admin';

    if (!isSecretAuthorized && !isAdmin) {
      return apiError('Unauthorized. Admin session or valid CRON_SECRET required.', 401);
    }

    if (!jobName || !REGISTERED_JOBS[jobName]) {
      return apiError(`Unknown or missing job name.`, 422);
    }

    const result = await executeJob(jobName, {
      dryRun,
      fix,
      batchSize,
      period: period || undefined,
    });

    return apiSuccess(result, `Job "${jobName}" executed successfully.`);
  } catch (err: any) {
    return apiError(err.message || 'Job execution failed', 500);
  }
}
