import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const title = (body.title || body.claim_type || '').trim();
    const description = (body.description || body.reason || '').trim();
    const amount = Number(body.amount || body.requested_amount || 0);
    const caseType = body.case_type || body.category || 'medical';

    if (!title || !description || amount <= 0) {
      return apiError('Title, description, and valid claim amount are required.', 422);
    }

    const newCase = await prisma.welfareCases.create({
      data: {
        related_member_id: session.userId,
        case_type: caseType,
        title,
        description,
        requested_amount: amount,
        target_amount: amount,
        status: 'pending',
        created_by: session.userId,
      },
    });

    return apiSuccess(newCase, 'Welfare claim submitted successfully and queued for committee review.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to submit welfare claim', 500);
  }
}
