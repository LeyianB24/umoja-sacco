import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { createNotification } from '@/lib/notifications';

export async function POST(request: NextRequest) {
  let rawBody = '';
  let logId: number | null = null;

  try {
    rawBody = await request.text();
    let body: any = {};
    try {
      body = JSON.parse(rawBody);
    } catch {
      // Invalid JSON
      return NextResponse.json({ ResultCode: 1, ResultDesc: 'Invalid JSON payload' });
    }

    // 1. Log incoming callback in callback_logs table
    const log = await prisma.callbackLogs.create({
      data: {
        callback_type: 'STK_PUSH',
        raw_payload: rawBody,
        processed: false,
        received_at: new Date(),
      },
    }).catch(() => null);

    logId = log?.log_id || null;

    // Check if body contains stkCallback
    const stkCallback = body?.Body?.stkCallback;
    if (!stkCallback) {
      if (logId) {
        await prisma.callbackLogs.update({
          where: { log_id: logId },
          data: { last_error: 'Missing stkCallback object in payload' },
        }).catch(() => null);
      }
      return NextResponse.json({ ResultCode: 1, ResultDesc: 'Invalid M-Pesa callback format' });
    }

    const {
      MerchantRequestID: merchantRequestId,
      CheckoutRequestID: checkoutRequestId,
      ResultCode: resultCode,
      ResultDesc: resultDesc,
    } = stkCallback;

    // Update log with checkout details
    if (logId) {
      await prisma.callbackLogs.update({
        where: { log_id: logId },
        data: {
          merchant_request_id: merchantRequestId,
          checkout_request_id: checkoutRequestId,
          result_code: resultCode,
          result_desc: resultDesc,
        },
      }).catch(() => null);
    }

    // If customer cancelled or payment failed
    if (resultCode !== 0) {
      // Mark mpesa_requests as failed if exists
      await prisma.mpesaRequests.updateMany({
        where: { checkout_request_id: checkoutRequestId },
        data: {
          status: 'failed',
          updated_at: new Date(),
        },
      }).catch(() => null);

      if (logId) {
        await prisma.callbackLogs.update({
          where: { log_id: logId },
          data: {
            processed: true,
            processed_at: new Date(),
            last_error: `M-Pesa transaction cancelled or failed: ${resultDesc}`,
          },
        }).catch(() => null);
      }

      return NextResponse.json({ ResultCode: 0, ResultDesc: 'Callback processed with failure status' });
    }

    // 2. Successful Payment: ResultCode === 0
    let mpesaReceipt = '';
    let amount = 0;
    let phoneNumber = '';
    let transactionDate: Date = new Date();

    const items = stkCallback.CallbackMetadata?.Item || [];
    for (const item of items) {
      switch (item.Name) {
        case 'MpesaReceiptNumber':
          mpesaReceipt = String(item.Value);
          break;
        case 'Amount':
          amount = Number(item.Value);
          break;
        case 'PhoneNumber':
          phoneNumber = String(item.Value);
          break;
        case 'TransactionDate':
          // format: YYYYMMDDHHmmss
          const rawDate = String(item.Value);
          if (rawDate.length === 14) {
            const y = parseInt(rawDate.substring(0, 4), 10);
            const m = parseInt(rawDate.substring(4, 6), 10) - 1;
            const d = parseInt(rawDate.substring(6, 8), 10);
            const h = parseInt(rawDate.substring(8, 10), 10);
            const min = parseInt(rawDate.substring(10, 12), 10);
            const s = parseInt(rawDate.substring(12, 14), 10);
            transactionDate = new Date(Date.UTC(y, m, d, h, min, s));
          }
          break;
      }
    }

    // Lookup original request by checkout_request_id
    const mpesaReq = await prisma.mpesaRequests.findFirst({
      where: { checkout_request_id: checkoutRequestId },
    }).catch(() => null);

    let memberId = mpesaReq?.member_id || null;

    // If not found in mpesa_requests, lookup member by phone
    if (!memberId && phoneNumber) {
      const normalizedPhoneVariants = [
        phoneNumber,
        `+${phoneNumber}`,
        phoneNumber.startsWith('254') ? '0' + phoneNumber.slice(3) : phoneNumber,
      ];

      const member = await prisma.members.findFirst({
        where: {
          phone: { in: normalizedPhoneVariants },
        },
      }).catch(() => null);

      if (member) {
        memberId = member.member_id;
      }
    }

    // Update log with extracted metadata
    if (logId) {
      await prisma.callbackLogs.update({
        where: { log_id: logId },
        data: {
          mpesa_receipt_number: mpesaReceipt,
          amount,
          phone_number: phoneNumber,
          transaction_date: transactionDate,
          member_id: memberId || undefined,
          processed: true,
          processed_at: new Date(),
        },
      }).catch(() => null);
    }

    if (!memberId) {
      console.warn(`M-Pesa callback: No member matched for phone ${phoneNumber} or CheckoutID ${checkoutRequestId}`);
      return NextResponse.json({ ResultCode: 0, ResultDesc: 'Callback logged, but no matching member found' });
    }

    // Idempotency: Check if receipt already processed
    if (mpesaReceipt) {
      const existingTx = await prisma.transactions.findFirst({
        where: { reference_no: mpesaReceipt },
      }).catch(() => null);

      if (existingTx) {
        return NextResponse.json({ ResultCode: 0, ResultDesc: 'Already Processed' });
      }
    }

    // Determine payment type
    let paymentType = 'savings';
    if (mpesaReq?.notes && ['savings', 'shares', 'loan_repayment', 'registration', 'welfare'].includes(mpesaReq.notes)) {
      paymentType = mpesaReq.notes;
    }

    const refNo = mpesaReceipt || `MP-${paymentType.toUpperCase()}-${Date.now().toString().slice(-6)}`;

    // Update mpesa_requests status to completed
    if (mpesaReq) {
      await prisma.mpesaRequests.update({
        where: { id: mpesaReq.id },
        data: {
          status: 'completed',
          mpesa_receipt: mpesaReceipt,
          updated_at: new Date(),
        },
      }).catch(() => null);
    }

    // Record according to payment type
    if (paymentType === 'registration') {
      await prisma.members.update({
        where: { member_id: memberId },
        data: {
          reg_fee_paid: true,
          registration_fee_status: 'paid',
          status: 'active',
        },
      }).catch(() => null);
    } else if (paymentType === 'loan_repayment') {
      // Find active loan
      const activeLoan = await prisma.loans.findFirst({
        where: {
          member_id: memberId,
          status: { in: ['approved', 'disbursed', 'active', 'repaying'] },
        },
        orderBy: { loan_id: 'asc' },
      }).catch(() => null);

      if (activeLoan) {
        const newBalance = Math.max(0, Number(activeLoan.current_balance || activeLoan.amount || 0) - amount);
        const isPaidOff = newBalance <= 0;

        await prisma.loanRepayments.create({
          data: {
            loan_id: activeLoan.loan_id,
            amount_paid: amount,
            payment_date: transactionDate,
            payment_method: 'mpesa',
            reference_no: refNo,
            mpesa_receipt: mpesaReceipt,
            status: 'Completed',
          },
        }).catch(() => null);

        await prisma.loans.update({
          where: { loan_id: activeLoan.loan_id },
          data: {
            current_balance: newBalance,
            status: isPaidOff ? 'completed' : 'repaying',
          },
        }).catch(() => null);
      }
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
          created_at: transactionDate,
        },
      }).catch(() => null);

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
      }).catch(() => null);
    } else {
      // Default: Savings
      await prisma.savings.create({
        data: {
          member_id: memberId,
          amount,
          transaction_type: 'deposit',
          description: `M-Pesa Paybill Deposit (${refNo})`,
          reference_no: refNo,
          created_at: transactionDate,
        },
      }).catch(() => null);
    }

    // Record general transaction ledger entry
    await prisma.transactions.create({
      data: {
        member_id: memberId,
        amount,
        transaction_type: paymentType,
        type: 'credit',
        category: 'M-Pesa Paybill',
        reference_no: refNo,
        payment_channel: 'mpesa',
        mpesa_request_id: checkoutRequestId,
        description: `M-Pesa payment for ${paymentType} (Receipt: ${mpesaReceipt})`,
        transaction_date: transactionDate,
      },
    }).catch(() => null);

    // Record contribution record
    await prisma.contributions.create({
      data: {
        member_id: memberId,
        contribution_type: paymentType,
        amount,
        contribution_date: transactionDate,
        payment_method: 'M-Pesa',
        reference_no: refNo,
        status: 'active',
        callback_received_at: new Date(),
      },
    }).catch(() => null);

    // In-app Notification
    await createNotification({
      memberId,
      title: 'M-Pesa Payment Received',
      message: `Confirmed: KES ${amount.toLocaleString()} received via M-Pesa (${refNo}) for ${paymentType}.`,
      metadata: { refNo, amount, paymentType, mpesaReceipt },
    }).catch(() => null);

    return NextResponse.json({ ResultCode: 0, ResultDesc: 'Callback processed successfully' });
  } catch (err: any) {
    console.error('Mpesa callback processing error:', err);
    if (logId) {
      await prisma.callbackLogs.update({
        where: { log_id: logId },
        data: { last_error: err.message || 'Server error' },
      }).catch(() => null);
    }
    return NextResponse.json({ ResultCode: 0, ResultDesc: 'Accepted with internal warning' });
  }
}

export async function GET() {
  return NextResponse.json({
    status: 'online',
    service: 'Umoja SACCO M-Pesa STK Callback Gateway',
    timestamp: new Date().toISOString(),
  });
}
