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
    const status = searchParams.get('status') || undefined;
    const page = parseInt(searchParams.get('page') || '1', 10);
    const limit = parseInt(searchParams.get('limit') || '50', 10);

    const where: any = {};
    if (status && status !== 'all') {
      where.status = status;
    }

    const [loans, total] = await Promise.all([
      prisma.loans.findMany({
        where,
        orderBy: { loan_id: 'desc' },
        skip: (page - 1) * limit,
        take: limit,
      }).catch(() => []),
      prisma.loans.count({ where }).catch(() => 0),
    ]);

    return apiSuccess({
      loans,
      pagination: {
        page,
        limit,
        total,
        pages: Math.ceil(total / limit),
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch loans', 500);
  }
}

export async function PUT(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const loanId = parseInt(body.loan_id, 10);
    const action = body.action; // 'approve', 'reject', 'disburse'

    if (!loanId || !action) {
      return apiError('loan_id and action are required.', 422);
    }

    let status = 'approved';
    if (action === 'reject') status = 'rejected';
    if (action === 'disburse') status = 'disbursed';

    const updated = await prisma.loans.update({
      where: { loan_id: loanId },
      data: {
        status,
        ...(action === 'approve' ? { approval_date: new Date(), approved_by: session.userId } : {}),
        ...(action === 'disburse' ? { disbursement_date: new Date() } : {}),
      },
    });

    return apiSuccess(updated, `Loan ${action}d successfully.`);
  } catch (err: any) {
    return apiError(err.message || 'Failed to update loan status', 500);
  }
}
