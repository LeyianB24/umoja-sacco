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

    const admins = await prisma.admins.findMany({
      select: {
        admin_id: true,
        username: true,
        full_name: true,
        email: true,
        phone: true,
        role_id: true,
        created_at: true,
        last_login: true,
      },
      orderBy: { admin_id: 'asc' },
    });

    const roles = await prisma.roles.findMany();
    const roleMap = Object.fromEntries(roles.map((r) => [r.id, r.name]));

    const usersWithRoles = admins.map((u) => ({
      ...u,
      role_name: roleMap[u.role_id || 4] || 'staff',
    }));

    return apiSuccess({
      users: usersWithRoles,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch administrative users', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const username = (body.username || '').trim();
    const fullName = (body.full_name || body.name || '').trim();
    const email = (body.email || '').trim();
    const password = body.password || 'admin123';
    const roleId = Number(body.role_id || 4);
    const phone = body.phone || '';

    if (!username || !fullName || !email) {
      return apiError('Username, full name, and email are required.', 422);
    }

    const hashedPassword = await hashPassword(password);

    const newAdmin = await prisma.admins.create({
      data: {
        username,
        full_name: fullName,
        email,
        password: hashedPassword,
        role_id: roleId,
        phone,
      },
    });

    return apiSuccess({
      admin_id: newAdmin.admin_id,
      username: newAdmin.username,
      full_name: newAdmin.full_name,
      email: newAdmin.email,
    }, 'Administrative user created successfully.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to create administrative user', 500);
  }
}
