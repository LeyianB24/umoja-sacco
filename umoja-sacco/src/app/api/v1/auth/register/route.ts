import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { hashPassword, signToken } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const fullName = (body.full_name || '').trim();
    const nationalId = (body.national_id || '').trim();
    const phoneRaw = (body.phone || '').trim();
    const email = (body.email || '').trim();
    const password = body.password || '';
    const confirm = body.confirm_password || body.password_confirmation || '';
    const gender = body.gender || 'male';
    const dob = body.dob ? new Date(body.dob) : null;
    const occupation = (body.occupation || '').trim();
    const address = (body.address || '').trim();
    const nokName = (body.nok_name || body.next_of_kin_name || '').trim();
    const nokPhone = (body.nok_phone || body.next_of_kin_phone || '').trim();

    if (!fullName || !nationalId || !phoneRaw || !email || !password) {
      return apiError('Please fill in all required fields (Full Name, National ID, Phone, Email, Password).', 422);
    }

    if (password.length < 6) {
      return apiError('Password must be at least 6 characters.', 422);
    }

    if (confirm && password !== confirm) {
      return apiError('Passwords do not match.', 422);
    }

    // Normalize phone number to +254...
    let phone = phoneRaw.replace(/[^\d\+]/g, '');
    if (!phone.startsWith('+')) {
      if (/^0(\d{8,9})$/.test(phone)) {
        phone = '+254' + phone.substring(1);
      } else if (/^7(\d{8})$/.test(phone)) {
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
      return apiError('A member with that email, phone, or national ID already exists.', 409);
    }

    const hashedPassword = await hashPassword(password);

    // Generate Member Reg No: USMS-YYYY-XXXX
    const currentYear = new Date().getFullYear();
    const prefix = `USMS-${currentYear}`;
    const count = await prisma.members.count({
      where: { member_reg_no: { startsWith: prefix } },
    });
    const regNo = `${prefix}-${String(count + 1).padStart(4, '0')}`;

    const newMember = await prisma.members.create({
      data: {
        member_reg_no: regNo,
        full_name: fullName,
        national_id: nationalId,
        phone: phone,
        email: email,
        password: hashedPassword,
        join_date: new Date(),
        status: 'active',
        reg_fee_paid: false,
        gender: gender,
        dob: dob,
        occupation: occupation,
        address: address,
        next_of_kin_name: nokName,
        next_of_kin_phone: nokPhone,
        kyc_status: 'pending',
      },
    });

    const token = signToken({
      userId: newMember.member_id,
      userType: 'member',
      role: 'member',
      email: newMember.email || undefined,
    });

    const response = apiSuccess(
      {
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
        redirect_to: '/member',
      },
      'Registration successful! Welcome to Umoja Sacco.',
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
