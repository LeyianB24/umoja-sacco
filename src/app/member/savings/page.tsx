'use client';

import React, { useEffect, useState } from 'react';
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
  PiggyBank,
  Wallet,
  Plus,
  ArrowDownRight,
  ArrowUpRight,
  TrendingUp,
  CreditCard,
  X,
  Smartphone,
} from 'lucide-react';

export default function MemberSavingsPage() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Deposit Modal
  const [depositModal, setDepositModal] = useState(false);
  const [depositAmount, setDepositAmount] = useState('1000');
  const [depositPhone, setDepositPhone] = useState(user?.phone || '');
  const [submitting, setSubmitting] = useState(false);

  const fetchSavings = async () => {
    try {
      const res = await api.get('/member/savings');
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
    fetchSavings();
  }, []);

  const handleDeposit = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(depositAmount);
    if (isNaN(amt) || amt <= 0) {
      toast.error('Please enter a valid amount.');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: amt,
        phone: depositPhone,
        type: 'savings',
      });
      toast.success('M-Pesa STK Prompt dispatched to your phone. Enter PIN to complete.');
      setDepositModal(false);
      setDepositAmount('1000');
      fetchSavings();
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Deposit failed.');
    } finally {
      setSubmitting(false);
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
        <div>Loading Savings Portfolio...</div>
      </div>
    );
  }

  const totalSavings = data?.total_savings || 0;
  const walletBalance = data?.wallet_balance || 0;
  const transactions = data?.transactions || [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h1 className="heading-1" style={{ margin: 0 }}>Savings Portfolio</h1>
          <p className="body-rg" style={{ margin: '4px 0 0 0' }}>
            Compulsory monthly savings and voluntary high-yield deposits
          </p>
        </div>
        <Button
          variant="primary"
          size="md"
          pill
          onClick={() => setDepositModal(true)}
        >
          <Plus size={16} strokeWidth={2.5} />
          <span>Deposit to Savings</span>
        </Button>
      </div>

      {/* Hero Balance Card */}
      <Card variant="elevated">
        <Card.Body>
          <BalanceHero
            label="Total Cumulative Savings"
            accountNumber={user?.reg_no || 'UDS-2026'}
            amount={totalSavings}
            currency="KES"
            onAddMoney={() => setDepositModal(true)}
          />
        </Card.Body>
      </Card>

      {/* Metrics Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-lime-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <PiggyBank size={20} />
            </div>
            <Badge status="active">Compounding</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Annual Interest Rate</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-charcoal)', marginTop: '2px' }}>
            3.75% APY
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Credited monthly to your account
          </div>
        </Card>

        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-gray-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Wallet size={20} />
            </div>
            <Badge variant="info">Instant</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Withdrawable Wallet Balance</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatKES(walletBalance)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Available for instant M-Pesa payout
          </div>
        </Card>
      </div>

      {/* Savings Ledger */}
      <Card variant="default">
        <Card.Header>
          <div>
            <h3 className="heading-2" style={{ fontSize: '18px', margin: 0 }}>Savings Transactions Ledger</h3>
            <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Audit history of your deposits and interest earnings</p>
          </div>
        </Card.Header>
        <Card.Body>
          {transactions.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {transactions.map((tx: any, idx: number) => {
                const isCredit = tx.credit > 0;
                return (
                  <ListItem
                    key={idx}
                    icon={isCredit ? <ArrowDownRight size={18} color="#16a34a" /> : <ArrowUpRight size={18} color="#dc2626" />}
                    label={tx.description || 'Savings Contribution'}
                    time={`${formatDate(tx.created_at)} • Ref: ${tx.reference || 'N/A'}`}
                    amount={isCredit ? `+${formatKES(tx.credit)}` : `-${formatKES(tx.debit)}`}
                    type={isCredit ? 'credit' : 'debit'}
                  />
                );
              })}
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '36px', color: 'var(--color-gray-medium)', fontSize: '14px' }}>
              No savings entries found. Start by making your first deposit.
            </div>
          )}
        </Card.Body>
      </Card>

      {/* Deposit Modal */}
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
                <h3 className="heading-3" style={{ margin: 0 }}>Deposit to Savings</h3>
                <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Instant M-Pesa STK push</p>
              </div>
              <button
                type="button"
                onClick={() => setDepositModal(false)}
                style={{ width: '32px', height: '32px', borderRadius: '50%', border: 'none', background: 'var(--color-gray-light)', color: 'var(--color-gray-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleDeposit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <Input
                label="Deposit Amount (KES)"
                type="number"
                min="50"
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
                helperText="Enter phone registered with M-Pesa"
                required
              />

              <Button
                type="submit"
                variant="primary"
                size="lg"
                pill
                disabled={submitting}
                style={{ width: '100%', marginTop: '8px' }}
              >
                {submitting ? 'Sending STK Prompt...' : `Deposit ${formatKES(parseFloat(depositAmount) || 0)}`}
              </Button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
