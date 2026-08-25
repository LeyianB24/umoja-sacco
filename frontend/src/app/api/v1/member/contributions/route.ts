import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const contributions = await prisma.contributions.findMany({
      where: { member_id: session.userId },
      orderBy: { created_at: 'desc' },
      take: 50,
    }).catch(() => []);

    return apiSuccess({
      contributions,
      total_count: contributions.length,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch member contributions', 500);
  }
}
