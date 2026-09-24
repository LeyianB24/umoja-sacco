import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession, hashPassword, verifyPassword } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const currentPassword = body.current_password || '';
    const newPassword = body.new_password || '';

    if (!currentPassword || !newPassword) {
      return apiError('Please provide both current and new password.', 422);
    }

    if (newPassword.length < 6) {
      return apiError('New password must be at least 6 characters long.', 422);
    }

    const member = await prisma.members.findUnique({
      where: { member_id: session.userId },
    });

    if (!member || !member.password) {
      return apiError('Member account not found.', 404);
    }

    const isValid = await verifyPassword(currentPassword, member.password);
    if (!isValid) {
      return apiError('The current password provided is incorrect.', 400);
    }

    const newHashed = await hashPassword(newPassword);
    await prisma.members.update({
      where: { member_id: session.userId },
      data: { password: newHashed },
    });

    return apiSuccess(null, 'Password changed successfully.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to change password', 500);
  }
}
