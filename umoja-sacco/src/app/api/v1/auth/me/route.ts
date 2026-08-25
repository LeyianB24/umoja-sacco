import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { getMemberBalances } from '@/lib/financial';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session) {
      return apiError('Unauthenticated', 401);
    }

    if (session.userType === 'admin') {
      const admin = await prisma.admins.findUnique({
        where: { admin_id: session.userId },
      });

      if (!admin) {
        return apiError('Admin not found', 404);
      }

      const role = admin.role_id
        ? await prisma.roles.findUnique({ where: { id: admin.role_id } })
        : null;

      const roleName = role?.name || (admin.role_id === 1 ? 'superadmin' : 'staff');

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

      // Unread notifications & messages
      const unreadNotifs = await prisma.notifications.count({
        where: { is_read: false },
      }).catch(() => 0);

      const unreadMsgs = await prisma.messages.count({
        where: { is_read: false },
      }).catch(() => 0);

      return apiSuccess({
        user: {
          id: admin.admin_id,
          name: admin.full_name || admin.username,
          username: admin.username,
          email: admin.email,
          phone: admin.phone || '',
          role: roleName.toLowerCase(),
          role_id: admin.role_id,
          role_name: roleName,
          last_login: admin.last_login,
          user_type: 'admin',
        },
        permissions,
        topbar: {
          unread_notifications: unreadNotifs,
          unread_messages: unreadMsgs,
          recent_notifications: [],
          recent_messages: [],
        },
      });
    }

    if (session.userType === 'member') {
      const member = await prisma.members.findUnique({
        where: { member_id: session.userId },
      });

      if (!member) {
        return apiError('Member not found', 404);
      }

      // Compute balances dynamically
      const balances = await getMemberBalances(member.member_id);

      const unreadNotifs = await prisma.notifications.count({
        where: { member_id: member.member_id, is_read: false },
      }).catch(() => 0);

      const unreadMsgs = await prisma.messages.count({
        where: { to_member_id: member.member_id, is_read: false },
      }).catch(() => 0);

      return apiSuccess({
        user: {
          id: member.member_id,
          name: member.full_name,
          reg_no: member.member_reg_no,
          national_id: member.national_id,
          email: member.email,
          phone: member.phone,
          gender: member.gender || 'male',
          dob: member.dob,
          occupation: member.occupation,
          address: member.address,
          next_of_kin_name: member.next_of_kin_name,
          next_of_kin_phone: member.next_of_kin_phone,
          status: member.status,
          kyc_status: member.kyc_status || 'pending',
          role: 'member',
          user_type: 'member',
          created_at: member.created_at,
        },
        balances,
        permissions: [],
        topbar: {
          unread_notifications: unreadNotifs,
          unread_messages: unreadMsgs,
          recent_notifications: [],
          recent_messages: [],
        },
      });
    }

    return apiError('Invalid session type', 401);
  } catch (err: any) {
    console.error('Me Auth Error:', err);
    return apiError(err.message || 'Failed to fetch session', 500);
  }
}
