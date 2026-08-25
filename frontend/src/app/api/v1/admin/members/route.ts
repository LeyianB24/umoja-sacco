import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession, hashPassword } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const search = searchParams.get('search') || searchParams.get('q') || '';
    const status = searchParams.get('status') || '';
    const limit = parseInt(searchParams.get('limit') || '50', 10);
    const page = parseInt(searchParams.get('page') || '1', 10);

    const where: any = {};
    if (status && status !== 'all') {
      where.status = status;
    }
    if (search) {
      where.OR = [
        { full_name: { contains: search, mode: 'insensitive' } },
        { member_reg_no: { contains: search, mode: 'insensitive' } },
        { email: { contains: search, mode: 'insensitive' } },
        { phone: { contains: search, mode: 'insensitive' } },
        { national_id: { contains: search, mode: 'insensitive' } },
      ];
    }

    const [members, total] = await Promise.all([
      prisma.members.findMany({
        where,
        orderBy: { member_id: 'desc' },
        skip: (page - 1) * limit,
        take: limit,
      }).catch(() => []),
      prisma.members.count({ where }).catch(() => 0),
    ]);

    return apiSuccess({
      members,
      pagination: {
        page,
        limit,
        total,
        pages: Math.ceil(total / limit),
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch members', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const fullName = (body.full_name || '').trim();
    const nationalId = (body.national_id || '').trim();
    const phone = (body.phone || '').trim();
    const email = (body.email || '').trim();
    const password = body.password || 'member123';

    if (!fullName || !nationalId || !phone) {
      return apiError('Full Name, National ID, and Phone are required.', 422);
    }

    const hashedPassword = await hashPassword(password);

    const currentYear = new Date().getFullYear();
    const prefix = `USMS-${currentYear}`;
    const count = await prisma.members.count({
      where: { member_reg_no: { startsWith: prefix } },
    });
    const regNo = `${prefix}-${String(count + 1).padStart(4, '0')}`;

    const member = await prisma.members.create({
      data: {
        member_reg_no: regNo,
        full_name: fullName,
        national_id: nationalId,
        phone,
        email: email || undefined,
        password: hashedPassword,
        join_date: new Date(),
        status: 'active',
        kyc_status: body.kyc_status || 'verified',
        gender: body.gender || 'male',
        address: body.address || '',
        occupation: body.occupation || 'Driver',
        wallet_balance: 0,
        savings_balance: 0,
        shares_balance: 0,
      },
    });

    return apiSuccess(member, 'Member onboarding completed successfully.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to onboard member', 500);
  }
}
