import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { getMemberBalances } from '@/lib/financial';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const amount = Number(body.amount);
    const phone = (body.phone || body.phone_number || '').trim();
    const sourceLedger = body.source_ledger || 'savings';

    if (!amount || amount <= 0) {
      return apiError('Please enter a valid withdrawal amount.', 422);
    }

    const balances = await getMemberBalances(session.userId);
    if (balances.savings < amount) {
      return apiError(`Insufficient savings balance. Available balance: KES ${balances.savings.toLocaleString()}`, 400);
    }

    const refNo = `WD-${Date.now().toString().slice(-8)}`;

    const withdrawal = await prisma.withdrawalRequests.create({
      data: {
        member_id: session.userId,
        ref_no: refNo,
        amount,
        source_ledger: sourceLedger,
        phone_number: phone || '+254700000000',
        status: 'pending',
        notes: body.reason || 'Member portal withdrawal request',
      },
    });

    return apiSuccess(withdrawal, 'Withdrawal request submitted successfully and queued for approval.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to submit withdrawal request', 500);
  }
}
