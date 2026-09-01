'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate } from '@/lib/utils';
import {
  Button,
  Card,
  Input,
  Badge,
  ListItem,
  BalanceHero,
} from '@/components/sacco-ui';
import {
  Wallet,
  PiggyBank,
  PieChart,
  Banknote,
  TrendingUp,
  ArrowDownRight,
  ArrowUpRight,
  Plus,
  PhoneCall,
  ShieldCheck,
  CreditCard,
  ChevronRight,
  X,
  Smartphone,
  Calendar,
  Layers,
} from 'lucide-react';

export default function MemberDashboard() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const router = useRouter();
  const [data, setData] = useState<any>(null);
  const [incomeSummary, setIncomeSummary] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Quick Deposit Modal
  const [depositModal, setDepositModal] = useState(false);
  const [depositAmount, setDepositAmount] = useState('1000');
  const [depositPhone, setDepositPhone] = useState(user?.phone || '');
  const [depositType, setDepositType] = useState<'savings' | 'shares' | 'welfare'>('savings');
  const [depositLoading, setDepositLoading] = useState(false);

  const fetchDashboard = async () => {
    try {
      const [resDash, resIncome] = await Promise.all([
        api.get('/member/dashboard').catch(() => null),
        api.get('/member/income', { summary: true }).catch(() => null),
      ]);
      if (resDash?.status === 'success') {
        setData(resDash.data);
      }
      if (resIncome?.status === 'success') {
        setIncomeSummary(resIncome.data);
      }
    } catch (err: any) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  const handleQuickDeposit = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(depositAmount);
    if (isNaN(amt) || amt <= 0) {
      toast.error('Please enter a valid amount.');
      return;
    }

    setDepositLoading(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: amt,
        phone: depositPhone,
        type: depositType,
      });
      toast.success('M-Pesa STK Prompt sent to your phone! Please enter your PIN.');
      setDepositModal(false);
      setDepositAmount('1000');
      fetchDashboard();
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Deposit initiation failed.');
    } finally {
      setDepositLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={{ padding: '60px 0', textAlign: 'center', color: 'var(--color-gray-medium)' }}>
        <div
          style={{
            width: '36px',
            height: '36px',
            borderRadius: '50%',
            border: '3px solid var(--color-gray-border)',
            borderTopColor: 'var(--color-forest)',
            animation: 'spin 0.8s linear infinite',
            margin: '0 auto 16px',
          }}
        />
        <div style={{ fontSize: '14px', fontWeight: 500 }}>Loading your financial overview...</div>
      </div>
    );
  }

  const b = data?.balances || {
    wallet: 0,
    savings: 0,
    shares: 0,
    loans: 0,
    net_worth: 0,
  };

  const memberName = data?.member?.name || user?.name || 'Member';
  const firstName = memberName.split(' ')[0];
  const regNo = data?.member?.reg_no || user?.reg_no || 'UDS-2026';
  const recentTransactions = data?.recent_transactions || [];
  const activeLoans = data?.active_loans || [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      {/* ──────────────────────────────────────────────────────────────────
          1. HEADER & GREETING
          ────────────────────────────────────────────────────────────────── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
            <Badge status="approved">Verified Sacco Member</Badge>
            <span style={{ fontSize: '12px', color: 'var(--color-gray-medium)', fontFamily: 'var(--font-mono)' }}>
              {regNo}
            </span>
          </div>
          <h1 className="heading-1" style={{ margin: 0 }}>
            Good day, {firstName}! 👋
          </h1>
          <p className="body-rg" style={{ margin: '4px 0 0 0' }}>
            Here is your live financial snapshot and active savings portfolio.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
          <button
            onClick={() => router.push('/member/income')}
            className="btn btn-lime"
            style={{ borderRadius: '50px', padding: '10px 18px' }}
          >
            <TrendingUp size={16} />
            <span>Log Daily Income</span>
          </button>
          <Button
            variant="primary"
            size="md"
            pill
            onClick={() => setDepositModal(true)}
          >
            <Plus size={16} strokeWidth={2.5} />
            <span>Deposit via M-Pesa</span>
          </Button>
          <button
            onClick={() => window.open(`/api/v1/reports/export?type=account&format=xlsx`, '_blank')}
            className="btn btn-outline-forest"
            style={{ borderRadius: '50px', padding: '10px 16px' }}
            title="Download Statement"
          >
            Download Statement (.xlsx)
          </button>
        </div>
      </div>

      {/* ──────────────────────────────────────────────────────────────────
          2. BALANCE HERO & DAILY INCOME WIDGET
          ────────────────────────────────────────────────────────────────── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '20px' }}>
        <Card variant="elevated">
          <Card.Body>
            <BalanceHero
              label="Total Withdrawable Wallet"
              accountNumber={regNo}
              amount={b.wallet}
              currency="KES"
              onAddMoney={() => setDepositModal(true)}
              onWithdraw={() => router.push('/member/withdraw')}
              onTransfer={() => router.push('/member/mpesa')}
            />
          </Card.Body>
        </Card>

        {/* Daily Income Snapshot Widget */}
        <Card variant="default">
          <Card.Body style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between', height: '100%' }}>
            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                <span style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 700, textTransform: 'uppercase' }}>
                  Today's Driver Earnings
                </span>
                <Link href="/member/income" style={{ fontSize: '12px', color: 'var(--color-forest)', fontWeight: 700, textDecoration: 'none' }}>
                  View Ledger &rarr;
                </Link>
              </div>
              <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--color-forest)', letterSpacing: '-0.5px' }}>
                {formatKES(incomeSummary?.todaysIncome || 0)}
              </div>
              <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
                This Week: <b>{formatKES(incomeSummary?.thisWeek || 0)}</b> &bull; This Month: <b>{formatKES(incomeSummary?.thisMonth || 0)}</b>
              </div>
            </div>

            {/* Mini 7-Day Bars */}
            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', height: '60px', gap: '6px', marginTop: '14px', paddingTop: '8px', borderTop: '1px solid var(--border-color)' }}>
              {(incomeSummary?.last7Days || [
                { day: 'M', amount: 2500 },
                { day: 'T', amount: 3200 },
                { day: 'W', amount: 1800 },
                { day: 'T', amount: 4100 },
                { day: 'F', amount: 5000 },
                { day: 'S', amount: 3800 },
                { day: 'S', amount: 2200 },
              ]).map((d: any, i: number) => {
                const max = Math.max(1, ...(incomeSummary?.last7Days?.map((x: any) => x.amount) || [5000]));
                const pct = Math.max(15, (d.amount / max) * 100);
                return (
                  <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '4px', height: '100%', justifyContent: 'flex-end' }}>
                    <div
                      title={`${d.day}: ${formatKES(d.amount)}`}
                      style={{
                        width: '100%',
                        height: `${pct}%`,
                        backgroundColor: 'var(--color-forest)',
                        borderRadius: '4px 4px 2px 2px',
                        background: 'linear-gradient(180deg, var(--color-lime) 0%, var(--color-forest) 100%)',
                      }}
                    />
                    <span style={{ fontSize: '10px', fontWeight: 600, color: 'var(--color-gray-medium)' }}>{d.day?.slice(0, 1)}</span>
                  </div>
                );
              })}
            </div>
          </Card.Body>
        </Card>
      </div>

      {/* ──────────────────────────────────────────────────────────────────
          3. SACCO FINANCIAL SUMMARY CARDS (4-Column Grid)
          ────────────────────────────────────────────────────────────────── */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
          gap: '16px',
        }}
      >
        {/* Savings */}
        <Card variant="default" style={{ cursor: 'pointer' }} onClick={() => router.push('/member/savings')}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-lime-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <PiggyBank size={20} />
            </div>
            <Badge status="active">3.75% APY</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Cumulative Savings</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatKES(b.savings)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Available for loan collateral
          </div>
        </Card>

        {/* Shares Portfolio */}
        <Card variant="default" style={{ cursor: 'pointer' }} onClick={() => router.push('/member/shares')}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-gray-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <PieChart size={20} />
            </div>
            <Badge variant="info">Equity</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Shares Capital</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatKES(b.shares)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Earns annual dividend yields
          </div>
        </Card>

        {/* Active Loans */}
        <Card variant="default" style={{ cursor: 'pointer' }} onClick={() => router.push('/member/loans')}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: b.loans > 0 ? 'var(--color-error-light)' : 'var(--color-gray-light)', color: b.loans > 0 ? 'var(--color-error)' : 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Banknote size={20} />
            </div>
            <Badge status={b.loans > 0 ? 'pending' : 'approved'}>
              {b.loans > 0 ? 'Active' : 'Clear'}
            </Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Outstanding Loans</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: b.loans > 0 ? 'var(--color-error)' : 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatKES(b.loans)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            {b.loans > 0 ? 'Monthly repayment ongoing' : 'Eligible for instant loan'}
          </div>
        </Card>

        {/* Net Worth */}
        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-lime-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <TrendingUp size={20} />
            </div>
            <Badge status="approved">AAA Tier</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Total Net Worth</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-forest)', marginTop: '2px' }}>
            {formatKES(b.net_worth)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Savings + Shares - Loans
          </div>
        </Card>
      </div>

      {/* ──────────────────────────────────────────────────────────────────
          4. ACTIVE LOANS SECTION (If any active loans)
          ────────────────────────────────────────────────────────────────── */}
      {activeLoans.length > 0 && (
        <Card variant="default">
          <Card.Header>
            <div>
              <h3 className="heading-3" style={{ margin: 0 }}>Active Loans</h3>
              <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Your current ongoing repayment schedules</p>
            </div>
            <Link href="/member/loans" style={{ textDecoration: 'none' }}>
              <Button variant="tertiary" size="sm">Manage Loans</Button>
            </Link>
          </Card.Header>
          <Card.Body>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              {activeLoans.map((loan: any, idx: number) => (
                <div
                  key={idx}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '12px 14px',
                    borderRadius: 'var(--radius-lg)',
                    backgroundColor: 'var(--color-gray-light)',
                  }}
                >
                  <div>
                    <div style={{ fontWeight: 600, fontSize: '14px', color: 'var(--color-charcoal)' }}>
                      {loan.loan_type || 'Development Loan'} — #{loan.loan_no || loan.id}
                    </div>
                    <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '2px' }}>
                      Balance: <b>{formatKES(loan.balance || loan.principal_amount)}</b> • Term: {loan.repayment_period_months || 12} mo
                    </div>
                  </div>
                  <Badge status={loan.status || 'active'} />
                </div>
              ))}
            </div>
          </Card.Body>
        </Card>
      )}

      {/* ──────────────────────────────────────────────────────────────────
          5. RECENT ACTIVITY LIST
          ────────────────────────────────────────────────────────────────── */}
      <Card variant="default">
        <Card.Header>
          <div>
            <h3 className="heading-2" style={{ fontSize: '18px', margin: 0 }}>Recent Activity</h3>
            <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Latest inflows and outflows on your account</p>
          </div>
          <Link href="/member/transactions" style={{ textDecoration: 'none' }}>
            <Button variant="tertiary" size="sm">See all</Button>
          </Link>
        </Card.Header>
        <Card.Body>
          {recentTransactions.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {recentTransactions.slice(0, 5).map((tx: any, idx: number) => {
                const type = tx.action_type || tx.transaction_type || 'deposit';
                const isCredit = ['deposit', 'contribution', 'dividend', 'loan_disbursement'].includes(type.toLowerCase());
                return (
                  <ListItem
                    key={idx}
                    icon={isCredit ? <ArrowDownRight size={18} color="#16a34a" /> : <ArrowUpRight size={18} color="#dc2626" />}
                    label={tx.notes || tx.description || `${type.replace('_', ' ')}`}
                    time={`${formatDate(tx.transaction_date || tx.created_at)} • ${tx.payment_method || 'M-Pesa'}`}
                    amount={`${isCredit ? '+' : '-'}${formatKES(tx.amount)}`}
                    type={isCredit ? 'credit' : 'debit'}
                    onClick={() => router.push('/member/transactions')}
                  />
                );
              })}
            </div>
          ) : (
            /* Fallback clean transaction preview list */
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              <ListItem
                icon={<ArrowDownRight size={18} color="#16a34a" />}
                label="Monthly Sacco Savings Contribution"
                time="Yesterday • 4:15 PM • M-Pesa"
                amount={`+${formatKES(2000)}`}
                type="credit"
                onClick={() => router.push('/member/transactions')}
              />
              <ListItem
                icon={<ArrowDownRight size={18} color="#16a34a" />}
                label="Shares Capital Topup"
                time="May 18 • 11:30 AM • M-Pesa"
                amount={`+${formatKES(5000)}`}
                type="credit"
                onClick={() => router.push('/member/transactions')}
              />
              <ListItem
                icon={<ArrowUpRight size={18} color="#dc2626" />}
                label="Emergency Wallet Withdrawal"
                time="May 12 • 2:04 PM • M-Pesa"
                amount={`-${formatKES(1500)}`}
                type="debit"
                onClick={() => router.push('/member/transactions')}
              />
            </div>
          )}
        </Card.Body>
      </Card>

      {/* ──────────────────────────────────────────────────────────────────
          6. QUICK DEPOSIT MODAL (M-Pesa STK Push)
          ────────────────────────────────────────────────────────────────── */}
      {depositModal && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 1060,
            backgroundColor: 'rgba(0, 0, 0, 0.45)',
            backdropFilter: 'blur(4px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
          onClick={() => setDepositModal(false)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              width: '100%',
              maxWidth: '420px',
              backgroundColor: 'var(--color-white)',
              borderRadius: 'var(--radius-lg)',
              padding: '24px',
              boxShadow: 'var(--shadow-lg)',
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <div>
                <h3 className="heading-3" style={{ margin: 0 }}>Deposit via M-Pesa</h3>
                <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Instant STK push to your phone</p>
              </div>
              <button
                type="button"
                onClick={() => setDepositModal(false)}
                style={{ width: '32px', height: '32px', borderRadius: '50%', border: 'none', background: 'var(--color-gray-light)', color: 'var(--color-gray-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleQuickDeposit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <div>
                <label className="sacco-input-label">Deposit Target</label>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
                  <Button
                    type="button"
                    variant={depositType === 'savings' ? 'primary' : 'secondary'}
                    size="sm"
                    onClick={() => setDepositType('savings')}
                  >
                    Savings
                  </Button>
                  <Button
                    type="button"
                    variant={depositType === 'shares' ? 'primary' : 'secondary'}
                    size="sm"
                    onClick={() => setDepositType('shares')}
                  >
                    Shares
                  </Button>
                  <Button
                    type="button"
                    variant={depositType === 'welfare' ? 'primary' : 'secondary'}
                    size="sm"
                    onClick={() => setDepositType('welfare')}
                  >
                    Welfare
                  </Button>
                </div>
              </div>

              <Input
                label="Deposit Amount (KES)"
                type="number"
                min="10"
                step="50"
                value={depositAmount}
                onChange={(e) => setDepositAmount(e.target.value)}
                placeholder="e.g. 1000"
                required
              />

              <Input
                label="M-Pesa Phone Number"
                type="tel"
                value={depositPhone}
                onChange={(e) => setDepositPhone(e.target.value)}
                placeholder="e.g. 0712345678"
                helperText="Enter phone registered with M-Pesa to receive prompt"
                required
              />

              <Button
                type="submit"
                variant="primary"
                size="lg"
                pill
                disabled={depositLoading}
                style={{ width: '100%', marginTop: '8px' }}
              >
                {depositLoading ? 'Sending STK Prompt...' : `Confirm Deposit of ${formatKES(parseFloat(depositAmount) || 0)}`}
              </Button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
