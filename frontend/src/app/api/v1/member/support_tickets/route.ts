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

    const tickets = await prisma.supportTickets.findMany({
      where: { member_id: session.userId },
      orderBy: { created_at: 'desc' },
    }).catch(() => []);

    return apiSuccess({ tickets });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch support tickets', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const subject = (body.subject || '').trim();
    const message = (body.message || body.body || '').trim();
    const category = body.category || 'general';
    const priority = body.priority || 'medium';

    if (!subject || !message) {
      return apiError('Subject and message are required.', 422);
    }

    const newTicket = await prisma.supportTickets.create({
      data: {
        member_id: session.userId,
        subject,
        message,
        category,
        priority,
        status: 'Pending',
      },
    });

    return apiSuccess(newTicket, 'Support ticket submitted successfully.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to submit support ticket', 500);
  }
}
