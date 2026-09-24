import { prisma } from './prisma';
import { sendEmail } from './email';

export interface CreateNotificationParams {
  memberId?: number;
  adminId?: number;
  userType?: 'member' | 'admin' | 'all';
  toRole?: string;
  title: string;
  message: string;
  commsType?: 'in-app' | 'sms' | 'email' | 'all';
  metadata?: Record<string, any>;
  recipientEmail?: string;
}

/**
 * Dispatches an in-app notification and optionally an email/SMS notification
 */
export async function createNotification({
  memberId,
  adminId,
  userType = 'member',
  toRole = 'member',
  title,
  message,
  commsType = 'in-app',
  metadata,
  recipientEmail,
}: CreateNotificationParams) {
  try {
    const notif = await prisma.notifications.create({
      data: {
        member_id: memberId || null,
        admin_id: adminId || null,
        user_type: userType,
        user_id: memberId || adminId || null,
        to_role: toRole,
        title,
        message,
        comms_type: commsType,
        status: 'sent',
        delivery_status: 'sent',
        metadata: metadata ? JSON.stringify(metadata) : null,
        is_read: false,
        created_at: new Date(),
      },
    });

    // If recipient email is provided or available, trigger email dispatch in background
    if (recipientEmail) {
      sendEmail({
        to: recipientEmail,
        subject: `[Umoja SACCO] ${title}`,
        html: `
          <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px;">
            <div style="background-color: #0B2419; padding: 16px; border-radius: 6px; text-align: center; margin-bottom: 20px;">
              <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Umoja SACCO Management System</h2>
            </div>
            <h3 style="color: #0B2419; margin-top: 0;">${title}</h3>
            <p style="color: #374151; font-size: 15px; line-height: 1.6;">${message}</p>
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />
            <p style="color: #6b7280; font-size: 12px; text-align: center;">
              This is an automated notification from Umoja Drivers SACCO Ltd. Please do not reply directly to this email.
            </p>
          </div>
        `,
      }).catch((err) => console.error('[Notification Email Error]:', err));
    }

    return notif;
  } catch (err: any) {
    console.error('[Create Notification Error]:', err);
    return null;
  }
}
