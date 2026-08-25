'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate } from '@/lib/utils';
import {
  Wallet,
  PiggyBank,
  PieChart,
  Banknote,
  TrendingUp,
  Activity,
  ArrowUpRight,
  ArrowDownRight,
  PlusCircle,
  PhoneCall,
  ShieldCheck,
  Calendar,
  AlertCircle,
  CheckCircle2,
  XCircle,
  Coins,
  Heart,
  FileText,
} from 'lucide-react';

export default function MemberDashboard() {
  const { user } = useAuth();
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Quick Deposit Modal
  const [depositModal, setDepositModal] = useState(false);
  const [depositAmount, setDepositAmount] = useState('');
  const [depositPhone, setDepositPhone] = useState(user?.phone || '');
  const [depositLoading, setDepositLoading] = useState(false);

  const fetchDashboard = async () => {
    try {
      const res = await api.get('/member/dashboard');
      if (res.status === 'success') {
        setData(res.data);
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
    if (isNaN(amt) || amt <= 0) return;

    setDepositLoading(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: amt,
        phone: depositPhone,
        type: 'savings',
      });
      toast.success('M-Pesa STK Prompt sent to your phone! Please enter your PIN.');
      setDepositModal(false);
      setDepositAmount('');
      fetchDashboard();
    } catch (err: any) {
      toast.error(err.message || 'Deposit initiation failed.');
    } finally {
      setDepositLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '80px 0', color: 'var(--text-muted)' }}>
        <div style={{ width: '40px', height: '40px', borderRadius: '50%', border: '3px solid var(--border-color)', borderTopColor: 'var(--brand-lime)', animation: 'spin 1s linear infinite', margin: '0 auto 16px' }} />
        Loading your financial overview...
      </div>
    );
  }

  const b = data?.balances || {
    wallet: 0,
    savings: 0,
    shares: 0,
    loans: 0,
    net_worth: 0,
    loan_limit: 500000,
    loan_pct: 0,
    health_score: 95,
  };

  const memberName = data?.member?.name || user?.name || 'Member';
  const firstName = memberName.split(' ')[0];
  const regNo = data?.member?.reg_no || user?.reg_no || 'USMS-2026';
  const joinDate = data?.member?.join_date || 'May 2026';
  const creditGrade = b.loan_pct < 30 ? 'AAA' : b.loan_pct < 50 ? 'AA+' : b.loan_pct < 70 ? 'A+' : 'B+';

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ═════════════════════════════════════════════════════════════════════
          1. HERO HEADER SECTION
      ═════════════════════════════════════════════════════════════════════ */}
      <div className="hp-hero" style={{ padding: '36px 40px', marginBottom: 0 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '30px', alignItems: 'flex-end' }}>
          <div>
            <div className="eyebrow-pill" style={{ marginBottom: '14px' }}>
              <span className="eyebrow-dot" /> Verified Sacco Member
            </div>
            <h1 style={{ fontSize: 'clamp(1.8rem, 3.5vw, 2.4rem)', fontWeight: 800, color: '#FFFFFF', letterSpacing: '-0.5px', marginBottom: '8px' }}>
              Good day, {firstName}! 👋
            </h1>
            <p style={{ color: 'rgba(255, 255, 255, 0.75)', fontSize: '0.88rem', marginBottom: '24px' }}>
              Member <strong style={{ color: '#FFFFFF' }}>{regNo}</strong> &nbsp;•&nbsp; Since <strong style={{ color: '#FFFFFF' }}>{joinDate}</strong> &nbsp;•&nbsp; Health Score <strong style={{ color: 'var(--brand-lime)' }}>{b.health_score}/100</strong>
            </p>

            {/* Hero Financial Bubbles */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', marginBottom: '28px' }}>
              <div style={{ backgroundColor: 'rgba(255, 255, 255, 0.1)', border: '1px solid rgba(255, 255, 255, 0.15)', borderRadius: '14px', padding: '10px 16px', minWidth: '100px' }}>
                <div style={{ fontSize: '0.95rem', fontWeight: 800, color: '#FFFFFF' }}>{formatKES(b.savings)}</div>
                <div style={{ fontSize: '0.65rem', fontWeight: 700, textTransform: 'uppercase', color: 'rgba(255, 255, 255, 0.5)', marginTop: '2px' }}>Savings</div>
              </div>
              <div style={{ backgroundColor: 'rgba(255, 255, 255, 0.1)', border: '1px solid rgba(255, 255, 255, 0.15)', borderRadius: '14px', padding: '10px 16px', minWidth: '100px' }}>
                <div style={{ fontSize: '0.95rem', fontWeight: 800, color: '#FFFFFF' }}>{formatKES(b.shares)}</div>
                <div style={{ fontSize: '0.65rem', fontWeight: 700, textTransform: 'uppercase', color: 'rgba(255, 255, 255, 0.5)', marginTop: '2px' }}>Shares</div>
              </div>
              <div style={{ backgroundColor: 'rgba(255, 255, 255, 0.1)', border: '1px solid rgba(255, 255, 255, 0.15)', borderRadius: '14px', padding: '10px 16px', minWidth: '100px' }}>
                <div style={{ fontSize: '0.95rem', fontWeight: 800, color: b.loans > 0 ? '#fca5a5' : 'var(--brand-lime)' }}>{formatKES(b.loans)}</div>
                <div style={{ fontSize: '0.65rem', fontWeight: 700, textTransform: 'uppercase', color: 'rgba(255, 255, 255, 0.5)', marginTop: '2px' }}>Loans</div>
              </div>
              <div style={{ backgroundColor: 'rgba(255, 255, 255, 0.1)', border: '1px solid rgba(255, 255, 255, 0.15)', borderRadius: '14px', padding: '10px 16px', minWidth: '100px' }}>
                <div style={{ fontSize: '0.95rem', fontWeight: 800, color: 'var(--brand-lime)' }}>{formatKES(b.net_worth)}</div>
                <div style={{ fontSize: '0.65rem', fontWeight: 700, textTransform: 'uppercase', color: 'rgba(255, 255, 255, 0.5)', marginTop: '2px' }}>Net Worth</div>
              </div>
              <div style={{ backgroundColor: 'rgba(255, 255, 255, 0.1)', border: '1px solid rgba(255, 255, 255, 0.15)', borderRadius: '14px', padding: '10px 16px', minWidth: '100px' }}>
                <div style={{ fontSize: '0.95rem', fontWeight: 800, color: '#FFFFFF' }}>{formatKES(b.wallet)}</div>
                <div style={{ fontSize: '0.65rem', fontWeight: 700, textTransform: 'uppercase', color: 'rgba(255, 255, 255, 0.5)', marginTop: '2px' }}>Wallet</div>
              </div>
            </div>

            {/* Hero Action Buttons */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
              <button onClick={() => setDepositModal(true)} className="btn btn-lime">
                <PlusCircle size={16} /> Quick Deposit
              </button>
              <Link href="/member/withdraw" className="btn btn-outline-lime">
                <ArrowUpRight size={16} /> Withdraw
              </Link>
              <Link href="/member/loans" className="btn btn-outline-lime">
                <Banknote size={16} /> Apply Loan
              </Link>
              <Link href="/member/transactions" className="btn btn-outline-lime">
                Ledger View
              </Link>
            </div>
          </div>

          {/* Right Credit Grade Card */}
          <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
            <div style={{ fontSize: '0.72rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '1px', color: 'rgba(255, 255, 255, 0.6)' }}>
              Credit Grade
            </div>
            <div style={{ fontSize: '3.6rem', fontWeight: 800, color: 'var(--brand-lime)', letterSpacing: '-2px', lineHeight: 1 }}>
              {creditGrade}
            </div>
            <div style={{ fontSize: '0.75rem', color: 'rgba(255, 255, 255, 0.65)', marginBottom: '14px' }}>
              Based on loan utilization
            </div>
            <div style={{ backgroundColor: 'rgba(255, 255, 255, 0.1)', borderRadius: '12px', padding: '12px 16px', width: '200px', textAlign: 'left' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.7rem', color: 'rgba(255, 255, 255, 0.7)', textTransform: 'uppercase', fontWeight: 700, marginBottom: '6px' }}>
                <span>Loan Limit</span>
                <span>{b.loan_pct}%</span>
              </div>
              <div style={{ width: '100%', height: '6px', backgroundColor: 'rgba(255, 255, 255, 0.2)', borderRadius: '10px', overflow: 'hidden' }}>
                <div style={{ width: `${Math.min(100, b.loan_pct)}%`, height: '100%', backgroundColor: 'var(--brand-lime)', borderRadius: '10px' }} />
              </div>
              <div style={{ fontSize: '0.68rem', color: 'rgba(255, 255, 255, 0.5)', marginTop: '4px' }}>
                Limit: {formatKES(b.loan_limit)}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ═════════════════════════════════════════════════════════════════════
          2. FLOATING STAT CARDS
      ═════════════════════════════════════════════════════════════════════ */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
        <div className="card card-hover" style={{ borderLeft: '4px solid #16a34a' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
            <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.8px' }}>Total Savings</span>
            <div style={{ width: '38px', height: '38px', borderRadius: '10px', backgroundColor: 'rgba(22, 163, 74, 0.1)', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <PiggyBank size={20} />
            </div>
          </div>
          <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '8px' }}>
            {formatKES(b.savings)}
          </div>
          <div style={{ fontSize: '0.78rem', color: '#16a34a', fontWeight: 600 }}>
            Active Compounding Savings
          </div>
        </div>

        <div className="card card-hover" style={{ borderLeft: '4px solid #2563eb' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
            <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.8px' }}>This Month</span>
            <div style={{ width: '38px', height: '38px', borderRadius: '10px', backgroundColor: 'rgba(37, 99, 235, 0.1)', color: '#2563eb', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Calendar size={20} />
            </div>
          </div>
          <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '8px' }}>
            {formatKES(data?.month_contrib || 2000)}
          </div>
          <div style={{ fontSize: '0.78rem', color: '#2563eb', fontWeight: 600 }}>
            Contribution Up to Date
          </div>
        </div>

        <div className="card card-hover" style={{ borderLeft: '4px solid #d97706' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
            <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.8px' }}>Total Deposits</span>
            <div style={{ width: '38px', height: '38px', borderRadius: '10px', backgroundColor: 'rgba(217, 119, 6, 0.1)', color: '#d97706', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <TrendingUp size={20} />
            </div>
          </div>
          <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '8px' }}>
            {formatKES(data?.total_deposits || b.savings)}
          </div>
          <div style={{ fontSize: '0.78rem', color: '#d97706', fontWeight: 600 }}>
            All-Time Cumulative Deposits
          </div>
        </div>

        <div className="card card-hover" style={{ borderLeft: '4px solid #dc2626' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
            <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.8px' }}>Total Withdrawn</span>
            <div style={{ width: '38px', height: '38px', borderRadius: '10px', backgroundColor: 'rgba(220, 38, 38, 0.1)', color: '#dc2626', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <ArrowUpRight size={20} />
            </div>
          </div>
          <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '8px' }}>
            {formatKES(data?.total_withdrawals || 0)}
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600 }}>
            Total Outflows to M-Pesa
          </div>
        </div>
      </div>

      {/* ═════════════════════════════════════════════════════════════════════
          3. HEALTH SCORE + RECENT TRANSACTIONS + QUICK ACTIONS
      ═════════════════════════════════════════════ */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '24px' }}>
        {/* Composite Health Breakdown */}
        <div className="card">
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '16px' }}>Account Health & Rating</h3>
          <div style={{ textAlign: 'center', marginBottom: '20px' }}>
            <div style={{ fontSize: '2.5rem', fontWeight: 800, color: 'var(--brand-forest)', lineHeight: 1 }}>{b.health_score}</div>
            <span className="badge badge-success" style={{ marginTop: '6px' }}>
              {b.health_score >= 80 ? 'Excellent Standing' : 'Good Rating'}
            </span>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid var(--border-subtle)', fontSize: '0.85rem' }}>
              <span style={{ color: 'var(--text-muted)' }}>Loan Utilization (&lt;50%):</span>
              <span style={{ fontWeight: 700, color: '#16a34a' }}>✓ Optimal ({b.loan_pct}%)</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid var(--border-subtle)', fontSize: '0.85rem' }}>
              <span style={{ color: 'var(--text-muted)' }}>Monthly Savings Contrib:</span>
              <span style={{ fontWeight: 700, color: '#16a34a' }}>✓ Active ({formatKES(2000)})</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid var(--border-subtle)', fontSize: '0.85rem' }}>
              <span style={{ color: 'var(--text-muted)' }}>Core Savings Balance:</span>
              <span style={{ fontWeight: 700, color: '#16a34a' }}>✓ Qualified</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', fontSize: '0.85rem' }}>
              <span style={{ color: 'var(--text-muted)' }}>Welfare Fund Coverage:</span>
              <span style={{ fontWeight: 700, color: '#16a34a' }}>✓ Active Member</span>
            </div>
          </div>
        </div>

        {/* Quick Operations Matrix */}
        <div className="card">
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '16px' }}>Quick Financial Actions</h3>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <button
              onClick={() => setDepositModal(true)}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                padding: '14px',
                borderRadius: '14px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textAlign: 'left',
                cursor: 'pointer',
              }}
            >
              <div style={{ width: '34px', height: '34px', borderRadius: '10px', backgroundColor: 'rgba(22, 163, 74, 0.1)', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <PlusCircle size={18} />
              </div>
              <div>
                <div style={{ fontWeight: 800, fontSize: '0.85rem' }}>Deposit</div>
                <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>Via M-Pesa</div>
              </div>
            </button>

            <Link
              href="/member/shares"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                padding: '14px',
                borderRadius: '14px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textAlign: 'left',
              }}
            >
              <div style={{ width: '34px', height: '34px', borderRadius: '10px', backgroundColor: 'rgba(208, 247, 100, 0.2)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Coins size={18} />
              </div>
              <div>
                <div style={{ fontWeight: 800, fontSize: '0.85rem' }}>Buy Shares</div>
                <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>Co-op Equity</div>
              </div>
            </Link>

            <Link
              href="/member/welfare"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                padding: '14px',
                borderRadius: '14px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textAlign: 'left',
              }}
            >
              <div style={{ width: '34px', height: '34px', borderRadius: '10px', backgroundColor: 'rgba(220, 38, 38, 0.1)', color: '#dc2626', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Heart size={18} />
              </div>
              <div>
                <div style={{ fontWeight: 800, fontSize: '0.85rem' }}>Welfare</div>
                <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>Claims & Aid</div>
              </div>
            </Link>

            <Link
              href="/member/loans"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                padding: '14px',
                borderRadius: '14px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textAlign: 'left',
              }}
            >
              <div style={{ width: '34px', height: '34px', borderRadius: '10px', backgroundColor: 'rgba(217, 119, 6, 0.1)', color: '#d97706', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Banknote size={18} />
              </div>
              <div>
                <div style={{ fontWeight: 800, fontSize: '0.85rem' }}>Apply Loan</div>
                <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>Instant Credit</div>
              </div>
            </Link>
          </div>
        </div>
      </div>

      {/* ═════════════════════════════════════════════════════════════════════
          4. RECENT ACTIVITY TABLE
      ═════════════════════════════════════════════ */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div
          style={{
            padding: '20px 24px',
            borderBottom: '1px solid var(--border-color)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <div>
            <h3 style={{ fontSize: '1.05rem', fontWeight: 700, color: 'var(--text-main)' }}>Recent Transactions</h3>
            <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Latest updates across your accounts</span>
          </div>
          <Link href="/member/transactions" style={{ fontSize: '0.85rem', color: 'var(--brand-forest)', fontWeight: 700 }}>
            View Full Ledger →
          </Link>
        </div>

        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Type / Description</th>
                <th>Amount</th>
                <th>Reference</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {data?.recent_transactions?.length ? (
                data.recent_transactions.map((tx: any, idx: number) => {
                  const isCredit = ['deposit', 'contribution', 'dividend', 'loan_disbursement'].includes(tx.type?.toLowerCase());
                  return (
                    <tr key={idx}>
                      <td style={{ fontWeight: 600, textTransform: 'capitalize' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          {isCredit ? (
                            <ArrowDownRight size={16} color="#16a34a" />
                          ) : (
                            <ArrowUpRight size={16} color="#dc2626" />
                          )}
                          {tx.type?.replace('_', ' ')}
                        </div>
                      </td>
                      <td style={{ fontWeight: 800, color: isCredit ? '#16a34a' : 'var(--text-main)' }}>
                        {isCredit ? '+' : '-'}{formatKES(tx.amount)}
                      </td>
                      <td>
                        <span style={{ fontFamily: 'monospace', fontSize: '0.8rem', backgroundColor: 'var(--surface-2)', padding: '2px 6px', borderRadius: '4px' }}>
                          {tx.reference || 'N/A'}
                        </span>
                      </td>
                      <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>
                        {formatDate(tx.created_at)}
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={4} style={{ textAlign: 'center', padding: '32px', color: 'var(--text-muted)' }}>
                    No recent transactions found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* ─── Quick Deposit Modal ─── */}
      <Modal isOpen={depositModal} onClose={() => setDepositModal(false)} title="Quick M-Pesa Deposit">
        <form onSubmit={handleQuickDeposit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">M-Pesa Phone Number</label>
            <input
              type="text"
              className="input-control"
              placeholder="e.g. 0712345678"
              value={depositPhone}
              onChange={(e) => setDepositPhone(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="input-label">Deposit Amount (KES)</label>
            <input
              type="number"
              min={100}
              step={50}
              className="input-control"
              placeholder="e.g. 2000"
              value={depositAmount}
              onChange={(e) => setDepositAmount(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={depositLoading} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {depositLoading ? 'Initiating Prompt...' : 'Send M-Pesa Prompt'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
