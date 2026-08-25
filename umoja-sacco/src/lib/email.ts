import nodemailer from 'nodemailer';

const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST || 'smtp.gmail.com',
  port: parseInt(process.env.SMTP_PORT || '587', 10),
  secure: process.env.SMTP_SECURE === 'true',
  auth: {
    user: process.env.SMTP_USERNAME || '',
    pass: process.env.SMTP_PASSWORD || '',
  },
});

export interface SendEmailOptions {
  to: string | string[];
  subject: string;
  html: string;
  text?: string;
}

export async function sendEmail({ to, subject, html, text }: SendEmailOptions) {
  if (!process.env.SMTP_USERNAME) {
    console.log(`[Email Mock] To: ${to} | Subject: ${subject}`);
    return { mock: true, success: true };
  }

  return transporter.sendMail({
    from: process.env.SMTP_FROM || 'Umoja SACCO <noreply@umojasacco.co.ke>',
    to,
    subject,
    html,
    text: text || html.replace(/<[^>]*>?/gm, ''),
  });
}
