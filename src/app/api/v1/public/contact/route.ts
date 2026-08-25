import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { sendEmail } from '@/lib/email';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const name = (body.name || '').trim();
    const email = (body.email || '').trim();
    const phone = (body.phone || '').trim();
    const subject = (body.subject || 'Public Website Inquiry').trim();
    const message = (body.message || '').trim();

    if (!name || !email || !message) {
      return apiError('Name, email, and message are required.', 422);
    }

    // Save as support ticket or audit log
    await prisma.supportTickets.create({
      data: {
        member_id: 1, // System / Guest ticket
        subject: `[Public Web Inquiry] ${subject} - from ${name} (${email})`,
        message: `From: ${name}\nEmail: ${email}\nPhone: ${phone}\n\nMessage:\n${message}`,
        category: 'general',
        status: 'open',
        priority: 'medium',
        created_at: new Date(),
      },
    }).catch((err) => {
      console.warn('Failed to persist contact message to supportTickets:', err.message);
    });

    // Send confirmation email
    await sendEmail({
      to: email,
      subject: `Thank you for contacting Umoja SACCO: ${subject}`,
      html: `
        <h2>Hello ${name},</h2>
        <p>We have received your message regarding: <strong>${subject}</strong>.</p>
        <p>A member service officer will review your inquiry and get in touch within 24 business hours.</p>
        <br/>
        <p>Warm regards,<br/><strong>Umoja Drivers & Allied Sacco Society Ltd</strong></p>
      `,
    }).catch(() => {});

    return apiSuccess(null, 'Your message has been received! A customer support representative will get back to you shortly.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to submit contact request', 500);
  }
}
