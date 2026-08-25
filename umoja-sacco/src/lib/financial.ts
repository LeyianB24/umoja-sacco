import { prisma } from './prisma';

export interface MemberBalances {
  wallet: number;
  savings: number;
  shares: number;
  shares_valuation: number;
  loans: number;
  net_worth: number;
}

export async function getMemberBalances(memberId: number): Promise<MemberBalances> {
  // 1. Calculate savings total from savings ledger
  const savingsAgg = await prisma.savings.aggregate({
    where: { member_id: memberId },
    _sum: { amount: true },
  }).catch(() => null);
  const savings = Number(savingsAgg?._sum?.amount || 0);

  // 2. Calculate shareholding from member_shareholdings or shares table
  const shareholding = await prisma.memberShareholdings.findUnique({
    where: { member_id: memberId },
  }).catch(() => null);
  
  const sharesUnits = Number(shareholding?.units_owned || 0);
  const sharesValue = Number(shareholding?.total_amount_paid || (sharesUnits * 20));

  // 3. Calculate active loans balance
  const activeLoans = await prisma.loans.findMany({
    where: {
      member_id: memberId,
      status: { in: ['approved', 'disbursed', 'active', 'repaying'] },
    },
  }).catch(() => []);

  const loansTotal = activeLoans.reduce((sum, l) => sum + Number(l.current_balance || l.amount || 0), 0);

  // 4. Wallet balance
  const wallet = 0;

  return {
    wallet,
    savings,
    shares: sharesUnits > 0 ? sharesUnits : (sharesValue > 0 ? Math.floor(sharesValue / 20) : 0),
    shares_valuation: sharesValue,
    loans: loansTotal,
    net_worth: savings + sharesValue - loansTotal,
  };
}
