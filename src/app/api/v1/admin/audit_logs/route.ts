import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
export const dynamic = 'force-dynamic';
export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const search = (searchParams.get('search') || '').trim();
    const severity = searchParams.get('severity') || undefined;
    const format = searchParams.get('format');
    const page = parseInt(searchParams.get('page') || '1', 10);
    const limit = parseInt(searchParams.get('limit') || '50', 10);

    const where: any = {};
    if (severity && severity !== 'all') {
      where.severity = severity;
    }
    if (search) {
      where.OR = [
        { action: { contains: search, mode: 'insensitive' } },
        { details: { contains: search, mode: 'insensitive' } },
      ];
    }

    if (format === 'csv') {
      const logs = await prisma.auditLogs.findMany({
        where,
        orderBy: { created_at: 'desc' },
        take: 500,
      });

      let csvContent = 'Audit ID,Timestamp,User Type,User ID,Action,Severity,IP Address,Details\n';
      logs.forEach((l) => {
        const row = [
          l.audit_id,
          l.created_at ? l.created_at.toISOString() : '',
          l.user_type || 'admin',
          l.admin_id || l.user_id || '',
          `"${(l.action || '').replace(/"/g, '""')}"`,
          l.severity || 'info',
          l.ip_address || '',
          `"${(l.details || '').replace(/"/g, '""')}"`,
        ].join(',');
        csvContent += row + '\n';
      });

      return new NextResponse(csvContent, {
        headers: {
          'Content-Type': 'text/csv',
          'Content-Disposition': `attachment; filename="audit-logs-${Date.now()}.csv"`,
        },
      });
    }

    const [logs, total] = await Promise.all([
      prisma.auditLogs.findMany({
        where,
        orderBy: { created_at: 'desc' },
        skip: (page - 1) * limit,
        take: limit,
      }),
      prisma.auditLogs.count({ where }),
    ]);

    return apiSuccess({
      logs,
      pagination: {
        page,
        limit,
        total,
        pages: Math.ceil(total / limit),
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch audit logs', 500);
  }
}
