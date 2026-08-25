import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { sendEmail } from '@/lib/email';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const email = (body.email || '').trim();

    if (!email) {
      return apiError('Email address is required.', 422);
    }

    // Check if member or admin exists
    const [member, admin] = await Promise.all([
      prisma.members.findUnique({ where: { email } }).catch(() => null),
      prisma.admins.findUnique({ where: { email } }).catch(() => null),
    ]);

    if (member || admin) {
      const recipientName = member?.full_name || admin?.full_name || 'Member';
      // In production, generate secure token and reset link
      const resetLink = `http://localhost:3000/login?reset=1`;

      await sendEmail({
        to: email,
        subject: 'Password Reset Request - Umoja SACCO',
        html: `<p>Hello ${recipientName},</p>
<p>We received a request to reset your Umoja SACCO portal password.</p>
<p><a href="${resetLink}" style="display:inline-block;padding:10px 20px;background:#78b726;color:#ffffff;text-decoration:none;border-radius:6px;">Reset Password</a></p>
<p>If you did not request this, you can safely ignore this email.</p>`,
      }).catch((e) => console.warn('Forgot password email dispatch:', e.message));
    }

    // Always return success to prevent email enumeration
    return apiSuccess(null, 'If that email is registered, password recovery instructions have been sent.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to process password recovery request', 500);
  }
}
