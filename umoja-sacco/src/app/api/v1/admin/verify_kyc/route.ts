import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const memberId = Number(body.member_id || body.id);
    const status = body.status || 'verified'; // 'verified', 'rejected', 'pending'
    const notes = body.notes || '';

    if (!memberId) {
      return apiError('Member ID is required.', 422);
    }

    const updated = await prisma.members.update({
      where: { member_id: memberId },
      data: {
        kyc_status: status,
        kyc_notes: notes || undefined,
        updated_at: new Date(),
      },
    });

    return apiSuccess(updated, `Member KYC status updated to ${status}.`);
  } catch (err: any) {
    return apiError(err.message || 'Failed to update member KYC status', 500);
  }
}
