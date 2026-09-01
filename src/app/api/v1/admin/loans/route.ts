import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
import { createNotification } from '@/lib/notifications';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const status = searchParams.get('status') || undefined;
    const page = parseInt(searchParams.get('page') || '1', 10);
    const limit = parseInt(searchParams.get('limit') || '50', 10);

    const where: any = {};
    if (status && status !== 'all') {
      where.status = status;
    }

    const [loans, total] = await Promise.all([
      prisma.loans.findMany({
        where,
        orderBy: { loan_id: 'desc' },
        skip: (page - 1) * limit,
        take: limit,
      }).catch(() => []),
      prisma.loans.count({ where }).catch(() => 0),
    ]);

    return apiSuccess({
      loans,
      pagination: {
        page,
        limit,
        total,
        pages: Math.ceil(total / limit),
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch loans', 500);
  }
}

export async function PUT(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const loanId = parseInt(body.loan_id, 10);
    const action = body.action; // 'approve', 'reject', 'disburse'
    const notes = body.notes || '';

    if (!loanId || !action) {
      return apiError('loan_id and action are required.', 422);
    }

    const existingLoan = await prisma.loans.findUnique({
      where: { loan_id: loanId },
    });

    if (!existingLoan) {
      return apiError('Loan not found.', 404);
    }

    let status = 'approved';
    if (action === 'reject') status = 'rejected';
    if (action === 'disburse') status = 'disbursed';

    const updated = await prisma.loans.update({
      where: { loan_id: loanId },
      data: {
        status,
        notes: notes ? (existingLoan.notes ? `${existingLoan.notes}\n${notes}` : notes) : undefined,
        ...(action === 'approve' ? { approval_date: new Date(), approved_by: session.userId } : {}),
        ...(action === 'disburse' ? {
          disbursed_date: new Date(),
          disbursed_amount: existingLoan.amount,
          current_balance: existingLoan.total_payable || existingLoan.amount,
        } : {}),
      },
    });

    // Record audit log
    await prisma.auditLogs.create({
      data: {
        admin_id: session.userId,
        user_type: 'admin',
        member_id: existingLoan.member_id,
        action: `loan_${action}`,
        details: `Admin ${session.userId} marked loan #${loanId} (KES ${Number(existingLoan.amount).toLocaleString()}) as ${status}. Notes: ${notes || 'None'}`,
        created_at: new Date(),
      },
    }).catch(() => null);

    // Send Member Notification
    const member = await prisma.members.findUnique({
      where: { member_id: existingLoan.member_id },
      select: { email: true, full_name: true },
    }).catch(() => null);

    let notifTitle = 'Loan Status Update';
    let notifMsg = `Your loan application #${loanId} for KES ${Number(existingLoan.amount).toLocaleString()} has been updated to "${status}".`;

    if (action === 'approve') {
      notifTitle = 'Loan Approved! 🎉';
      notifMsg = `Great news! Your loan application #${loanId} for KES ${Number(existingLoan.amount).toLocaleString()} has been approved and is queued for disbursement.`;
    } else if (action === 'disburse') {
      notifTitle = 'Loan Disbursed! 💸';
      notifMsg = `Your loan #${loanId} for KES ${Number(existingLoan.amount).toLocaleString()} has been disbursed to your account. Repayment schedule is now active.`;
    } else if (action === 'reject') {
      notifTitle = 'Loan Application Rejected';
      notifMsg = `Your loan application #${loanId} for KES ${Number(existingLoan.amount).toLocaleString()} was not approved. Reason: ${notes || 'Credit policy requirements'}.`;
    }

    await createNotification({
      memberId: existingLoan.member_id,
      recipientEmail: member?.email,
      title: notifTitle,
      message: notifMsg,
      metadata: { loanId, status, amount: Number(existingLoan.amount) },
    });

    return apiSuccess(updated, `Loan ${action}d successfully.`);
  } catch (err: any) {
    return apiError(err.message || 'Failed to update loan status', 500);
  }
}

