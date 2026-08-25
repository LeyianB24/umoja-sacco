import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { getMemberBalances } from '@/lib/financial';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const member = await prisma.members.findUnique({
      where: { member_id: session.userId },
    });

    if (!member) {
      return apiError('Member profile not found', 404);
    }

    const balances = await getMemberBalances(session.userId);

    return apiSuccess({
      member,
      balances,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch member profile', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));

    const updated = await prisma.members.update({
      where: { member_id: session.userId },
      data: {
        phone: body.phone || undefined,
        address: body.address || undefined,
        occupation: body.occupation || undefined,
        next_of_kin_name: body.next_of_kin_name || undefined,
        next_of_kin_phone: body.next_of_kin_phone || undefined,
        updated_at: new Date(),
      },
    });

    return apiSuccess(updated, 'Profile details updated successfully.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to update member profile', 500);
  }
}
