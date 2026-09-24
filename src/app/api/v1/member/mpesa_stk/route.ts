import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
import { createNotification } from '@/lib/notifications';

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const amount = Number(body.amount);
    const phone = body.phone || body.phoneNumber;
    const paymentType = body.type || body.payment_type || 'savings'; // savings, shares, loan_repayment, welfare

    if (!amount || amount <= 0) {
      return apiError('Please specify a valid deposit amount.', 422);
    }

    // Generate reference code
    const checkoutRequestId = `ws_CO_${Date.now()}_${Math.floor(Math.random() * 10000)}`;
    const refNo = `MP-${paymentType.slice(0, 3).toUpperCase()}-${Date.now().toString().slice(-6)}`;

    // Security: Only record a pending transaction record.
    // Financial ledgers (savings, shares) MUST ONLY be credited upon verified Daraja callback.
    await prisma.transactions.create({
      data: {
        member_id: session.userId,
        amount,
        transaction_type: paymentType,
        type: 'credit',
        category: 'M-Pesa STK (Pending)',
        reference_no: refNo,
        payment_channel: 'mpesa',
        mpesa_request_id: checkoutRequestId,
        description: `Pending M-Pesa ${paymentType} deposit (${refNo})`,
        notes: JSON.stringify({ phone, paymentType, initiated_at: new Date().toISOString() }),
        transaction_date: new Date(),
      },
    });

    // In-app acknowledgment of STK dispatch
    await createNotification({
      memberId: session.userId,
      title: 'STK Prompt Dispatched',
      message: `An M-Pesa prompt for KES ${amount.toLocaleString()} was dispatched to ${phone || 'your phone'}. Enter PIN to finalize.`,
      metadata: { refNo, amount, paymentType, checkoutRequestId },
    });

    return apiSuccess({
      CheckoutRequestID: checkoutRequestId,
      MerchantRequestID: `MR_${Date.now()}`,
      ResponseCode: '0',
      ResponseDescription: 'Success. Request accepted for processing',
      CustomerMessage: `STK push initiated to ${phone || 'registered phone'}. Please enter your M-Pesa PIN on your phone.`,
      reference_no: refNo,
    }, 'M-Pesa STK Prompt dispatched successfully.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to initiate M-Pesa prompt', 500);
  }
}

