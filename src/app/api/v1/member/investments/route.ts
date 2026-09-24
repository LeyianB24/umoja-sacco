import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
export const dynamic = 'force-dynamic';
export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const investmentId = searchParams.get('id');

    if (investmentId) {
      const inv = await prisma.memberInvestments.findFirst({
        where: { investment_id: Number(investmentId), member_id: session.userId },
      });
      if (!inv) return apiError('Investment not found', 404);

      const priceHistory = await prisma.investmentPrices.findMany({
        where: { investment_id: Number(investmentId) },
        orderBy: { price_date: 'asc' },
      });

      return apiSuccess({ investment: inv, priceHistory });
    }

    const investments = await prisma.memberInvestments.findMany({
      where: { member_id: session.userId },
      orderBy: { purchase_date: 'desc' },
    });

    // Auto-update Fixed Deposits with compound interest
    const now = new Date();
    const updatedList = investments.map((inv) => {
      const cost = Number(inv.cost_price);
      let currVal = Number(inv.current_value || cost);
      let gainLoss = 0;
      let gainLossPercent = 0;

      if (inv.type === 'fixed_deposit' && inv.expected_return) {
        const pDate = new Date(inv.purchase_date);
        const daysElapsed = Math.max(0, Math.floor((now.getTime() - pDate.getTime()) / (1000 * 3600 * 24)));
        const rate = Number(inv.expected_return) / 100;
        currVal = Math.round(cost * Math.pow(1 + rate, daysElapsed / 365) * 100) / 100;
      }

      gainLoss = Math.round((currVal - cost) * 100) / 100;
      gainLossPercent = cost > 0 ? Math.round((gainLoss / cost) * 10000) / 100 : 0;

      return {
        ...inv,
        current_value: currVal,
        gain_loss: gainLoss,
        gain_loss_percent: gainLossPercent,
      };
    });

    const totalCost = updatedList.reduce((acc, i) => acc + Number(i.cost_price), 0);
    const totalValue = updatedList.reduce((acc, i) => acc + Number(i.current_value), 0);
    const totalGainLoss = Math.round((totalValue - totalCost) * 100) / 100;
    const totalGainLossPercent = totalCost > 0 ? Math.round((totalGainLoss / totalCost) * 10000) / 100 : 0;

    return apiSuccess({
      investments: updatedList,
      totalCost,
      totalValue,
      totalGainLoss,
      totalGainLossPercent,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch investments', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const type = body.type || 'stock';
    const assetName = (body.asset_name || body.assetName || '').trim();
    const purchaseDate = body.purchase_date ? new Date(body.purchase_date) : new Date();
    const quantity = Number(body.quantity || 1);
    const costPrice = Number(body.cost_price || body.costPrice || 0);
    const maturityDate = body.maturity_date ? new Date(body.maturity_date) : null;
    const expectedReturn = Number(body.expected_return || body.expectedReturn || 0);
    const notes = (body.notes || '').trim();

    if (!assetName || costPrice <= 0) {
      return apiError('Asset name and a valid purchase cost are required.', 422);
    }

    const totalInvestment = Math.round(quantity * costPrice * 100) / 100;

    const newInv = await prisma.memberInvestments.create({
      data: {
        member_id: session.userId,
        type,
        asset_name: assetName,
        purchase_date: purchaseDate,
        quantity,
        cost_price: totalInvestment,
        current_price: costPrice,
        current_value: totalInvestment,
        maturity_date: maturityDate,
        expected_return: expectedReturn,
        notes,
        status: 'active',
      },
    });

    // Record initial price point
    await prisma.investmentPrices.create({
      data: {
        investment_id: newInv.investment_id,
        price: totalInvestment,
        price_date: purchaseDate,
      },
    });

    return apiSuccess(newInv, `Investment added: ${assetName} — KES ${totalInvestment.toLocaleString()}`, 201);
  } catch (err: any) {
    return apiError(err.message || 'Failed to add investment', 500);
  }
}

export async function PUT(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const investmentId = Number(body.investment_id || body.investmentId);
    const newPrice = Number(body.current_price || body.newPrice || 0);

    if (!investmentId || newPrice <= 0) {
      return apiError('Valid investment ID and new price are required.', 422);
    }

    const inv = await prisma.memberInvestments.findFirst({
      where: { investment_id: investmentId, member_id: session.userId },
    });

    if (!inv) {
      return apiError('Investment record not found.', 404);
    }

    const quantity = Number(inv.quantity || 1);
    const updatedValue = Math.round(newPrice * quantity * 100) / 100;
    const cost = Number(inv.cost_price);
    const gainLoss = Math.round((updatedValue - cost) * 100) / 100;
    const gainLossPercent = cost > 0 ? Math.round((gainLoss / cost) * 10000) / 100 : 0;

    const updated = await prisma.memberInvestments.update({
      where: { investment_id: investmentId },
      data: {
        current_price: newPrice,
        current_value: updatedValue,
        updated_at: new Date(),
      },
    });

    // Append to price history
    await prisma.investmentPrices.create({
      data: {
        investment_id: investmentId,
        price: updatedValue,
        price_date: new Date(),
      },
    });

    return apiSuccess({
      success: true,
      currentValue: updatedValue,
      gainLoss,
      gainLossPercent,
    }, 'Asset valuation updated.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to update asset price', 500);
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const id = Number(searchParams.get('id'));

    if (!id) return apiError('Investment ID is required', 422);

    await prisma.investmentPrices.deleteMany({
      where: { investment_id: id },
    }).catch(() => null);

    await prisma.memberInvestments.delete({
      where: { investment_id: id },
    });

    return apiSuccess({ success: true }, 'Investment removed from your portfolio.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to delete investment', 500);
  }
}
