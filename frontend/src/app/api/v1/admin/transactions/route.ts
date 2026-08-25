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

    const { searchParams } = new URL(request.url);
    const type = searchParams.get('type') || undefined;
    const search = searchParams.get('search') || '';
    const page = parseInt(searchParams.get('page') || '1', 10);
    const limit = parseInt(searchParams.get('limit') || '50', 10);

    const where: any = {};
    if (type && type !== 'all') {
      where.transaction_type = type;
    }
    if (search) {
      where.OR = [
        { transaction_type: { contains: search, mode: 'insensitive' } },
        { description: { contains: search, mode: 'insensitive' } },
      ];
    }

    const [transactions, total] = await Promise.all([
      prisma.transactions.findMany({
        where,
        orderBy: { transaction_date: 'desc' },
        skip: (page - 1) * limit,
        take: limit,
      }).catch(() => []),
      prisma.transactions.count({ where }).catch(() => 0),
    ]);

    return apiSuccess({
      transactions,
      pagination: {
        page,
        limit,
        total,
        pages: Math.ceil(total / limit),
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch admin transactions', 500);
  }
}
