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

    // In production, initiate Safaricom Daraja STK push
    // Simulate instant recording in transactions & destination ledger
    if (paymentType === 'savings') {
      await prisma.savings.create({
        data: {
          member_id: session.userId,
          amount,
          transaction_type: 'deposit',
          description: `M-Pesa STK Deposit (${refNo})`,
          reference_no: refNo,
        },
      });
    } else if (paymentType === 'shares') {
      const units = Math.floor(amount / 20);
      await prisma.shareTransactions.create({
        data: {
          member_id: session.userId,
          units,
          unit_price: 20,
          total_value: amount,
          transaction_type: 'purchase',
          reference_no: refNo,
        },
      });

      await prisma.memberShareholdings.upsert({
        where: { member_id: session.userId },
        update: {
          units_owned: { increment: units },
          total_amount_paid: { increment: amount },
          last_updated: new Date(),
        },
        create: {
          member_id: session.userId,
          units_owned: units,
          total_amount_paid: amount,
          average_purchase_price: 20,
        },
      });
    }

    // Record general transaction
    await prisma.transactions.create({
      data: {
        member_id: session.userId,
        amount,
        transaction_type: paymentType,
        type: 'credit',
        category: 'M-Pesa Paybill',
        reference_no: refNo,
        payment_channel: 'mpesa',
        mpesa_request_id: checkoutRequestId,
        description: `M-Pesa payment for ${paymentType}`,
        transaction_date: new Date(),
      },
    });

    // Send in-app confirmation notification
    await createNotification({
      memberId: session.userId,
      title: 'Payment Received',
      message: `Your M-Pesa payment of KES ${amount.toLocaleString()} for ${paymentType} (Ref: ${refNo}) was received and credited to your account.`,
      metadata: { refNo, amount, paymentType },
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

