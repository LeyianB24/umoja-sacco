import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { hashPassword, signToken } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

function generateTempPassword(length = 8): string {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789#$!';
  let pass = '';
  for (let i = 0; i < length; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pass;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const fullName = (body.full_name || body.fullName || '').trim();
    const nationalId = (body.national_id || body.nationalId || '').trim();
    const phoneRaw = (body.phone || '').trim();
    const email = (body.email || '').trim().toLowerCase();
    let password = body.password || '';
    const confirm = body.confirm_password || body.password_confirmation || '';
    const gender = body.gender || 'male';
    const dobRaw = body.dob || body.dateOfBirth;
    const occupation = (body.occupation || 'Driver / Operator').trim();
    const address = (body.address || '').trim();
    const city = (body.city || 'Nairobi').trim();
    const nokName = (body.nok_name || body.next_of_kin_name || 'Next of Kin').trim();
    const nokPhone = (body.nok_phone || body.next_of_kin_phone || '').trim();

    if (!fullName || !nationalId || !phoneRaw || !email) {
      return apiError('Please fill in all required fields (Full Name, National ID, Phone, Email).', 422);
    }

    // Verify 18+ age if DOB provided
    let dob: Date | null = null;
    if (dobRaw) {
      dob = new Date(dobRaw);
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const m = today.getMonth() - dob.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
      }
      if (age < 18) {
        return apiError('Members must be at least 18 years of age to register.', 422);
      }
    }

    let isAutoTempPass = false;
    if (!password) {
      password = generateTempPassword(8);
      isAutoTempPass = true;
    } else {
      if (password.length < 6) {
        return apiError('Password must be at least 6 characters.', 422);
      }
      if (confirm && password !== confirm) {
        return apiError('Passwords do not match.', 422);
      }
    }

    // Normalize phone number to +254...
    let phone = phoneRaw.replace(/[^\d+]/g, '');
    if (!phone.startsWith('+')) {
      if (/^0(\d{8,9})$/.test(phone)) {
        phone = '+254' + phone.substring(1);
      } else if (/^[17](\d{8})$/.test(phone)) {
        phone = '+254' + phone;
      }
    }

    // Check duplicate
    const existing = await prisma.members.findFirst({
      where: {
        OR: [
          { email: email },
          { phone: phone },
          { national_id: nationalId },
        ],
      },
    });

    if (existing) {
      let duplicateField = 'email, phone, or national ID';
      if (existing.email === email) duplicateField = 'Email address';
      else if (existing.phone === phone) duplicateField = 'Phone number';
      else if (existing.national_id === nationalId) duplicateField = 'National ID number';
      return apiError(`A member with this ${duplicateField} is already registered.`, 409);
    }

    const hashedPassword = await hashPassword(password);

    // Generate Member Reg No: UMS-YYYY-XXXX
    const currentYear = new Date().getFullYear();
    const prefix = `UMS-${currentYear}`;
    const count = await prisma.members.count({
      where: { member_reg_no: { startsWith: prefix } },
    });
    const regNo = `${prefix}-${String(count + 1).padStart(4, '0')}`;

    const fullAddress = address ? (city ? `${address}, ${city}` : address) : city;

    const newMember = await prisma.members.create({
      data: {
        member_reg_no: regNo,
        full_name: fullName,
        national_id: nationalId,
        phone: phone,
        email: email,
        password: hashedPassword,
        temp_password: isAutoTempPass ? password : null,
        join_date: new Date(),
        status: 'pending',
        registration_fee_status: 'unpaid',
        reg_fee_paid: false,
        gender: gender,
        dob: dob,
        occupation: occupation,
        address: fullAddress,
        next_of_kin_name: nokName,
        next_of_kin_phone: nokPhone || phone,
        kyc_status: 'pending',
      },
    });

    // Queue Welcome Email
    try {
      await prisma.emailQueue.create({
        data: {
          recipient_email: email,
          recipient_name: fullName,
          subject: 'Welcome to Umoja Drivers SACCO - Account Created',
          body: `<h2>Welcome to Umoja Drivers SACCO!</h2>
<p>Dear <b>${fullName}</b>,</p>
<p>Your membership account has been registered successfully. Here are your credentials:</p>
<table style="border-collapse: collapse; margin: 16px 0;">
  <tr><td style="padding: 6px 12px; font-weight: bold;">Member Number:</td><td style="padding: 6px 12px; font-family: monospace; color: #0b2419;"><b>${regNo}</b></td></tr>
  <tr><td style="padding: 6px 12px; font-weight: bold;">Login Email:</td><td style="padding: 6px 12px;">${email}</td></tr>
  ${isAutoTempPass ? `<tr><td style="padding: 6px 12px; font-weight: bold;">Temporary Password:</td><td style="padding: 6px 12px; font-family: monospace; color: #0b2419; font-weight: bold;">${password}</td></tr>` : ''}
</table>
<p>Please log in and upload your profile picture to complete your profile setup.</p>
<p>Built with pride by <b>Bezalel Technologies</b>.<br/>Umoja SACCO Management</p>`,
          status: 'pending',
          priority: 10,
        },
      });
    } catch (queueErr) {
      console.warn('Could not queue welcome email:', queueErr);
    }

    // Auto-login session token
    const token = signToken({
      userId: newMember.member_id,
      userType: 'member',
      role: 'member',
      email: newMember.email || undefined,
    });

    const response = apiSuccess(
      {
        memberId: newMember.member_id,
        memberNumber: newMember.member_reg_no,
        tempPassword: isAutoTempPass ? password : null,
        user: {
          id: newMember.member_id,
          name: newMember.full_name,
          reg_no: newMember.member_reg_no,
          national_id: newMember.national_id,
          email: newMember.email,
          phone: newMember.phone,
          status: newMember.status,
          kyc_status: newMember.kyc_status,
          role: 'member',
          user_type: 'member',
        },
        token,
        redirect_to: '/member?welcome=1',
      },
      'Registration successful! Welcome to Umoja Drivers SACCO.',
      201
    );

    response.cookies.set('usms_token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'lax',
      maxAge: 60 * 60 * 24 * 7,
      path: '/',
    });

    return response;
  } catch (err: any) {
    console.error('Registration Error:', err);
    return apiError(err.message || 'Registration failed', 500);
  }
}
