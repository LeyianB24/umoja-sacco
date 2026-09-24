import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
export const dynamic = 'force-dynamic';
export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const recentBroadcasts = await prisma.emailQueue.findMany({
      where: { subject: { startsWith: '[BROADCAST]' } },
      orderBy: { created_at: 'desc' },
      take: 50,
    });

    const [totalQueued, totalSent, totalFailed] = await Promise.all([
      prisma.emailQueue.count({ where: { status: 'pending' } }),
      prisma.emailQueue.count({ where: { status: 'sent' } }),
      prisma.emailQueue.count({ where: { status: 'failed' } }),
    ]);

    return apiSuccess({
      stats: { totalQueued, totalSent, totalFailed },
      recentBroadcasts,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch broadcast logs', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const segment = body.segment || 'all'; // all, active, pending, borrowers
    const subject = (body.subject || '').trim();
    const message = (body.message || '').trim();

    if (!subject || !message) {
      return apiError('Subject and message content are required.', 422);
    }

    // Filter recipients based on segment
    let whereClause: any = {};
    if (segment === 'active') whereClause = { status: 'active' };
    else if (segment === 'pending') whereClause = { status: 'pending' };

    const members = await prisma.members.findMany({
      where: whereClause,
      select: { member_id: true, full_name: true, email: true },
    });

    if (members.length === 0) {
      return apiError('No members found in selected audience segment.', 404);
    }

    // Bulk queue emails
    const formattedSubject = `[BROADCAST] ${subject}`;
    const emailRecords = members
      .filter((m) => m.email && m.email.includes('@'))
      .map((m) => ({
        recipient_email: m.email,
        recipient_name: m.full_name,
        subject: formattedSubject,
        body: `<div style="font-family: sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto;">
  <div style="background: #0b2419; padding: 20px; border-radius: 12px 12px 0 0; text-align: center; color: #ffffff;">
    <h2 style="margin: 0; font-size: 20px;">UMOJA DRIVERS SACCO</h2>
    <span style="color: #a3e635; font-size: 12px; font-weight: bold; text-transform: uppercase;">Official Member Notice</span>
  </div>
  <div style="padding: 24px; background: #ffffff; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px;">
    <p>Dear <b>${m.full_name}</b>,</p>
    <div style="margin: 16px 0;">${message.replace(/\n/g, '<br/>')}</div>
    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;" />
    <p style="font-size: 12px; color: #777777;">
      Sent via Umoja SACCO Broadcast Center &bull; Built by Bezalel Technologies<br/>
      If you have questions, please contact our support desk.
    </p>
  </div>
</div>`,
        priority: 7,
        status: 'pending',
      }));

    await prisma.emailQueue.createMany({
      data: emailRecords,
    });

    // Record in Audit Log
    await prisma.auditLogs.create({
      data: {
        admin_id: session.userId,
        user_type: 'admin',
        action: 'EMAIL_BROADCAST_DISPATCHED',
        details: `Broadcast "${subject}" dispatched to ${emailRecords.length} member(s) in "${segment}" segment.`,
        created_at: new Date(),
      },
    }).catch(() => null);

    return apiSuccess({
      queuedCount: emailRecords.length,
      segment,
    }, `Broadcast queued for ${emailRecords.length} member(s).`, 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to dispatch broadcast', 500);
  }
}
