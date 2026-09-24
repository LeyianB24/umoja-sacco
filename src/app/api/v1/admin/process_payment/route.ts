import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const memberId = Number(body.member_id);
    const amount = Number(body.amount);
    const paymentType = body.payment_type || 'savings'; // 'savings', 'shares', 'loan_repayment', 'fee'
    const channel = body.payment_channel || 'cash';
    const notes = body.notes || body.description || 'Manual payment entry by admin';

    if (!memberId || !amount || amount <= 0) {
      return apiError('Member ID and valid amount are required.', 422);
    }

    const refNo = `MAN-${paymentType.slice(0, 3).toUpperCase()}-${Date.now().toString().slice(-6)}`;

    // 1. Record specific destination
    if (paymentType === 'savings') {
      await prisma.savings.create({
        data: {
          member_id: memberId,
          amount,
          transaction_type: 'deposit',
          description: notes,
          reference_no: refNo,
        },
      });
    } else if (paymentType === 'shares') {
      const units = Math.floor(amount / 20);
      await prisma.shareTransactions.create({
        data: {
          member_id: memberId,
          units,
          unit_price: 20,
          total_value: amount,
          transaction_type: 'purchase',
          reference_no: refNo,
        },
      });
      await prisma.memberShareholdings.upsert({
        where: { member_id: memberId },
        update: {
          units_owned: { increment: units },
          total_amount_paid: { increment: amount },
          last_updated: new Date(),
        },
        create: {
          member_id: memberId,
          units_owned: units,
          total_amount_paid: amount,
          average_purchase_price: 20,
        },
      });
    }

    // 2. Record in main transactions table
    const transaction = await prisma.transactions.create({
      data: {
        member_id: memberId,
        amount,
        transaction_type: paymentType,
        type: 'credit',
        category: 'Counter/Manual Payment',
        reference_no: refNo,
        payment_channel: channel,
        description: notes,
        created_by_admin: session.userId,
        transaction_date: new Date(),
      },
    });

    return apiSuccess(transaction, 'Payment processed and recorded successfully.', 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to process payment', 500);
  }
}
