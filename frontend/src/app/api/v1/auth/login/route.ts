import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { verifyPassword, signToken } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const email = (body.email || body.username || body.identifier || '').trim();
    const password = body.password || '';
    const userType = body.user_type || 'auto'; // 'auto', 'admin', 'member'

    if (!email || !password) {
      return apiError('Please provide both username/email and password.', 422);
    }

    // 1. Try Staff / Admin Login
    if (userType === 'auto' || userType === 'admin') {
      const admin = await prisma.admins.findFirst({
        where: {
          OR: [{ email: email }, { username: email }],
        },
      });

      if (admin && admin.password && (await verifyPassword(password, admin.password))) {
        // Fetch role if exists
        const role = admin.role_id
          ? await prisma.roles.findUnique({ where: { id: admin.role_id } })
          : null;

        const roleName = role?.name || (admin.role_id === 1 ? 'superadmin' : 'staff');
        
        // Fetch permissions
        let permissions: string[] = [];
        if (admin.role_id === 1) {
          const allPerms = await prisma.permissions.findMany({ select: { slug: true } });
          permissions = allPerms.map((p) => p.slug);
        } else if (admin.role_id) {
          const rolePerms = await prisma.rolePermissions.findMany({
            where: { role_id: admin.role_id },
          });
          const permIds = rolePerms.map((rp) => rp.permission_id);
          const perms = await prisma.permissions.findMany({
            where: { id: { in: permIds } },
            select: { slug: true },
          });
          permissions = perms.map((p) => p.slug);
        }

        // Update last login
        await prisma.admins.update({
          where: { admin_id: admin.admin_id },
          data: { last_login: new Date() },
        });

        const token = signToken({
          userId: admin.admin_id,
          userType: 'admin',
          roleId: admin.role_id || undefined,
          role: roleName,
          email: admin.email,
        });

        const response = apiSuccess(
          {
            user: {
              id: admin.admin_id,
              name: admin.full_name || admin.username,
              username: admin.username,
              email: admin.email,
              phone: admin.phone || '',
              role: roleName.toLowerCase(),
              role_id: admin.role_id,
              role_name: roleName,
              user_type: 'admin',
            },
            permissions,
            token,
            redirect_to: '/admin',
          },
          'Login successful.'
        );

        response.cookies.set('usms_token', token, {
          httpOnly: true,
          secure: process.env.NODE_ENV === 'production',
          sameSite: 'lax',
          maxAge: 60 * 60 * 24 * 7, // 7 days
          path: '/',
        });

        return response;
      }
    }

    // 2. Try Member Login
    if (userType === 'auto' || userType === 'member') {
      const member = await prisma.members.findFirst({
        where: {
          OR: [
            { email: email },
            { member_reg_no: email },
            { phone: email },
            { national_id: email },
          ],
        },
      });

      if (member && member.password && (await verifyPassword(password, member.password))) {
        const token = signToken({
          userId: member.member_id,
          userType: 'member',
          role: 'member',
          email: member.email || undefined,
        });

        const response = apiSuccess(
          {
            user: {
              id: member.member_id,
              name: member.full_name,
              reg_no: member.member_reg_no,
              national_id: member.national_id,
              email: member.email,
              phone: member.phone,
              status: member.status,
              kyc_status: member.kyc_status || 'pending',
              role: 'member',
              user_type: 'member',
            },
            permissions: [],
            token,
            redirect_to: '/member',
          },
          'Login successful.'
        );

        response.cookies.set('usms_token', token, {
          httpOnly: true,
          secure: process.env.NODE_ENV === 'production',
          sameSite: 'lax',
          maxAge: 60 * 60 * 24 * 7, // 7 days
          path: '/',
        });

        return response;
      }
    }

    return apiError('Invalid credentials. Please verify your email/identifier and password.', 401);
  } catch (err: any) {
    console.error('Login Error:', err);
    return apiError(err.message || 'Authentication error', 500);
  }
}
