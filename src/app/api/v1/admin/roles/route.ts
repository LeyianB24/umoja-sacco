import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const [roles, permissions, rolePermissions] = await Promise.all([
      prisma.roles.findMany().catch(() => []),
      prisma.permissions.findMany().catch(() => []),
      prisma.rolePermissions.findMany().catch(() => []),
    ]);

    return apiSuccess({
      roles,
      permissions,
      role_permissions: rolePermissions,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch roles and permissions', 500);
  }
}
