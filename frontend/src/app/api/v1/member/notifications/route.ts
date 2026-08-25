import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session) {
      return apiError('Unauthorized', 401);
    }

    const notifications = await prisma.notifications.findMany({
      where: session.userType === 'member'
        ? { member_id: session.userId }
        : {},
      orderBy: { created_at: 'desc' },
      take: 30,
    }).catch(() => []);

    return apiSuccess({
      notifications,
      unread_count: notifications.filter((n) => !n.is_read).length,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch notifications', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session) {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const notifId = body.notification_id ? Number(body.notification_id) : undefined;

    if (notifId) {
      await prisma.notifications.updateMany({
        where: { notification_id: notifId },
        data: { is_read: true },
      });
    } else {
      // Mark all as read for this member
      await prisma.notifications.updateMany({
        where: session.userType === 'member' ? { member_id: session.userId } : {},
        data: { is_read: true },
      });
    }

    return apiSuccess(null, 'Notifications marked as read.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to update notification status', 500);
  }
}
