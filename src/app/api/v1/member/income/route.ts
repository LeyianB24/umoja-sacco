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
    const filter = searchParams.get('filter') || 'all';
    const isSummary = searchParams.get('summary') === 'true';
    const search = (searchParams.get('search') || '').trim();
    const startDateParam = searchParams.get('startDate');
    const endDateParam = searchParams.get('endDate');

    const memberId = session.userId;
    const now = new Date();

    if (isSummary) {
      // 1. Calculate today's start and end
      const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      const todayEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);

      // 2. This week start (Monday)
      const dayOfWeek = now.getDay() || 7; // 1 = Monday, 7 = Sunday
      const weekStart = new Date(now);
      weekStart.setDate(now.getDate() - dayOfWeek + 1);
      weekStart.setHours(0, 0, 0, 0);

      // 3. This month start
      const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

      // Fetch aggregations
      const [todayAgg, weekAgg, monthAgg, allEntries] = await Promise.all([
        prisma.dailyIncome.aggregate({
          where: { member_id: memberId, date: { gte: todayStart, lte: todayEnd } },
          _sum: { amount: true },
        }),
        prisma.dailyIncome.aggregate({
          where: { member_id: memberId, date: { gte: weekStart } },
          _sum: { amount: true },
        }),
        prisma.dailyIncome.aggregate({
          where: { member_id: memberId, date: { gte: monthStart } },
          _sum: { amount: true },
        }),
        prisma.dailyIncome.findMany({
          where: {
            member_id: memberId,
            date: { gte: new Date(Date.now() - 7 * 24 * 3600 * 1000) },
          },
          orderBy: { date: 'asc' },
        }),
      ]);

      // Build 7-day daily breakdown
      const last7Days: { date: string; day: string; amount: number }[] = [];
      const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        const dateStr = d.toISOString().split('T')[0];
        const dayEntries = allEntries.filter((e) => e.date.toISOString().split('T')[0] === dateStr);
        const dayTotal = dayEntries.reduce((acc, curr) => acc + Number(curr.amount), 0);
        last7Days.push({
          date: dateStr,
          day: dayNames[d.getDay()],
          amount: dayTotal,
        });
      }

      return apiSuccess({
        todaysIncome: Number(todayAgg._sum.amount || 0),
        thisWeek: Number(weekAgg._sum.amount || 0),
        thisMonth: Number(monthAgg._sum.amount || 0),
        last7Days,
      });
    }

    // List Query with Filters
    let dateFilter: any = {};
    if (filter === 'today') {
      const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      dateFilter = { gte: todayStart };
    } else if (filter === 'week') {
      const dayOfWeek = now.getDay() || 7;
      const weekStart = new Date(now);
      weekStart.setDate(now.getDate() - dayOfWeek + 1);
      weekStart.setHours(0, 0, 0, 0);
      dateFilter = { gte: weekStart };
    } else if (filter === 'month') {
      const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
      dateFilter = { gte: monthStart };
    } else if (startDateParam && endDateParam) {
      dateFilter = {
        gte: new Date(startDateParam),
        lte: new Date(endDateParam),
      };
    }

    const where: any = {
      member_id: memberId,
      ...(Object.keys(dateFilter).length > 0 ? { date: dateFilter } : {}),
      ...(search ? {
        OR: [
          { notes: { contains: search, mode: 'insensitive' } },
          { source: { contains: search, mode: 'insensitive' } },
        ],
      } : {}),
    };

    const entries = await prisma.dailyIncome.findMany({
      where,
      orderBy: { date: 'desc' },
      take: 100,
    });

    const totalAmount = entries.reduce((acc, e) => acc + Number(e.amount), 0);

    return apiSuccess({
      data: entries,
      totalCount: entries.length,
      totalAmount,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch income records', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const amount = Number(body.amount);
    const dateStr = body.date || new Date().toISOString();
    const source = body.source || 'taxi_fares';
    const notes = (body.notes || '').trim();

    if (!amount || isNaN(amount) || amount <= 0) {
      return apiError('Please enter a valid income amount greater than KES 0.', 422);
    }

    if (amount > 100000) {
      return apiError('Single daily income entry cannot exceed KES 100,000.', 422);
    }

    const incomeDate = new Date(dateStr);
    const now = new Date();
    if (incomeDate > now) {
      return apiError('Income date cannot be in the future.', 422);
    }

    // 1. Create Income Record
    const income = await prisma.dailyIncome.create({
      data: {
        member_id: session.userId,
        date: incomeDate,
        amount,
        source,
        notes,
      },
    });

    // 2. Auto-Calculations:
    // - Default 10% auto savings (Mandatory Savings)
    // - Auto daily contribution (2% or min KES 50)
    const autoSavingsAmount = Math.round(amount * 0.10 * 100) / 100;
    const autoContributionAmount = Math.max(50, Math.round(amount * 0.02 * 100) / 100);

    const refNo = `INC-${income.income_id}`;

    // Create auto savings transaction
    await prisma.savings.create({
      data: {
        member_id: session.userId,
        amount: autoSavingsAmount,
        transaction_type: 'auto_daily_savings',
        description: `10% Auto Daily Savings from KES ${amount.toLocaleString()} ${source.replace(/_/g, ' ')}`,
        reference_no: refNo,
      },
    });

    // Create auto daily contribution transaction
    await prisma.contributions.create({
      data: {
        member_id: session.userId,
        contribution_type: 'daily_contribution',
        amount: autoContributionAmount,
        payment_method: 'Auto Deduct',
        reference_no: `${refNo}-CB`,
        status: 'completed',
      },
    });

    // Record general transaction entries
    await prisma.transactions.create({
      data: {
        member_id: session.userId,
        amount,
        transaction_type: 'daily_income',
        type: 'credit',
        category: 'Driver Daily Revenue',
        reference_no: refNo,
        description: `Daily income: ${source.replace(/_/g, ' ')}`,
        notes: notes || `Auto saved KES ${autoSavingsAmount}, Auto contributed KES ${autoContributionAmount}`,
        transaction_date: incomeDate,
      },
    });

    // Create In-App Notification
    await prisma.notifications.create({
      data: {
        member_id: session.userId,
        to_role: 'member',
        status: 'unread',
        title: 'Daily Income Logged',
        message: `KES ${amount.toLocaleString()} logged. KES ${autoSavingsAmount.toLocaleString()} saved to your account, and KES ${autoContributionAmount} added to daily contributions.`,
      },
    });

    return apiSuccess(
      {
        incomeId: income.income_id,
        createdAt: income.created_at,
        autoSavingsAmount,
        autoContributionAmount,
      },
      `Income recorded: KES ${amount.toLocaleString()} (KES ${autoSavingsAmount} saved, KES ${autoContributionAmount} contributed)`,
      201
    );
  } catch (err: any) {
    return apiError(err.message || 'Failed to record daily income', 500);
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const { searchParams } = new URL(request.url);
    const incomeId = Number(searchParams.get('id') || searchParams.get('incomeId'));

    if (!incomeId) {
      return apiError('Income ID is required.', 422);
    }

    const income = await prisma.dailyIncome.findFirst({
      where: { income_id: incomeId, member_id: session.userId },
    });

    if (!income) {
      return apiError('Income record not found or unauthorized.', 404);
    }

    const refNo = `INC-${income.income_id}`;

    // Reverse auto savings and contributions
    await Promise.all([
      prisma.savings.deleteMany({
        where: { member_id: session.userId, reference_no: refNo },
      }).catch(() => null),
      prisma.contributions.deleteMany({
        where: { member_id: session.userId, reference_no: `${refNo}-CB` },
      }).catch(() => null),
      prisma.transactions.deleteMany({
        where: { member_id: session.userId, reference_no: refNo },
      }).catch(() => null),
      prisma.dailyIncome.delete({
        where: { income_id: incomeId },
      }),
    ]);

    return apiSuccess({ success: true }, 'Income record and associated auto-transactions reversed successfully.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to delete income record', 500);
  }
}
