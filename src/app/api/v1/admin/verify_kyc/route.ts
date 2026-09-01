import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
import { createNotification } from '@/lib/notifications';

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

    // Notify member of KYC verification result
    const isApproved = status === 'verified';
    await createNotification({
      memberId,
      recipientEmail: updated.email,
      title: isApproved ? 'KYC Verification Approved' : 'KYC Verification Update',
      message: isApproved
        ? 'Congratulations! Your KYC documents have been verified and approved by SACCO management. You now have full access to loans and investment products.'
        : `Your KYC document submission status has been updated to "${status}". Notes: ${notes || 'Please review your uploaded documents or contact support.'}`,
      metadata: { status, notes },
    });

    return apiSuccess(updated, `Member KYC status updated to ${status}.`);
  } catch (err: any) {
    return apiError(err.message || 'Failed to update member KYC status', 500);
  }
}

