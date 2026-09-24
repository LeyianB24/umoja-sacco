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

    const [cases, donations, supportRequests] = await Promise.all([
      prisma.welfareCases.findMany({
        orderBy: { created_at: 'desc' },
      }).catch(() => []),
      prisma.welfareDonations.findMany({
        orderBy: { created_at: 'desc' },
        take: 50,
      }).catch(() => []),
      prisma.welfareSupport.findMany({
        orderBy: { created_at: 'desc' },
        take: 50,
      }).catch(() => []),
    ]);

    const totalRaised = cases.reduce((acc, c) => acc + Number(c.total_raised || 0), 0);
    const totalDisbursed = cases.reduce((acc, c) => acc + Number(c.total_disbursed || 0), 0);

    return apiSuccess({
      cases,
      donations,
      support_requests: supportRequests,
      summary: {
        total_cases: cases.length,
        active_cases: cases.filter((c) => c.status === 'active').length,
        total_raised: totalRaised,
        total_disbursed: totalDisbursed,
        fund_balance: 1540000 + totalRaised - totalDisbursed,
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch welfare records', 500);
  }
}

export async function PUT(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const caseId = Number(body.case_id || body.id);
    const action = body.action; // 'approve', 'reject', 'close', 'disburse'
    const approvedAmount = body.approved_amount ? Number(body.approved_amount) : undefined;

    if (!caseId || !action) {
      return apiError('Case ID and action are required.', 422);
    }

    let status = 'approved';
    if (action === 'reject') status = 'rejected';
    if (action === 'close') status = 'closed';
    if (action === 'disburse') status = 'disbursed';

    const updated = await prisma.welfareCases.update({
      where: { case_id: caseId },
      data: {
        status,
        ...(approvedAmount ? { approved_amount: approvedAmount } : {}),
      },
    });

    return apiSuccess(updated, `Welfare case ${action}d successfully.`);
  } catch (err: any) {
    return apiError(err.message || 'Failed to update welfare case', 500);
  }
}
