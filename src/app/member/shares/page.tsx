'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate, formatNumber } from '@/lib/utils';
import {
  Button,
  Card,
  Input,
  Badge,
  ListItem,
  BalanceHero,
} from '@/components/sacco-ui';
import {
  PieChart,
  Coins,
  Award,
  Plus,
  ArrowDownRight,
  TrendingUp,
  X,
} from 'lucide-react';

export default function MemberSharesPage() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Buy Shares Modal
  const [buyModal, setBuyModal] = useState(false);
  const [sharesToBuy, setSharesToBuy] = useState('10');
  const [phone, setPhone] = useState(user?.phone || '');
  const [submitting, setSubmitting] = useState(false);

  const fetchShares = async () => {
    try {
      const res = await api.get('/member/shares');
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
    fetchShares();
  }, []);

  const unitPrice = data?.unit_price || 100;
  const numShares = parseInt(sharesToBuy) || 0;
  const totalCost = numShares * unitPrice;

  const handleBuyShares = async (e: React.FormEvent) => {
    e.preventDefault();
    if (totalCost <= 0) return;

    setSubmitting(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: totalCost,
        phone,
        type: 'shares',
      });
      toast.success(`M-Pesa STK Prompt for ${sharesToBuy} shares (${formatKES(totalCost)}) sent to your phone.`);
      setBuyModal(false);
      fetchShares();
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Share purchase failed.');
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
        <div>Loading Shares Portfolio...</div>
      </div>
    );
  }

  const totalShares = data?.total_shares || 0;
  const numSharesHeld = data?.num_shares || 0;
  const projectedDividend = totalShares * 0.145; // 14.5% yield
  const dividends = data?.dividends || [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h1 className="heading-1" style={{ margin: 0 }}>Shares & Equity Portfolio</h1>
          <p className="body-rg" style={{ margin: '4px 0 0 0' }}>
            Co-operative ownership equity, voting power, and annual dividend earnings
          </p>
        </div>
        <Button
          variant="primary"
          size="md"
          pill
          onClick={() => setBuyModal(true)}
        >
          <Plus size={16} strokeWidth={2.5} />
          <span>Purchase Shares</span>
        </Button>
      </div>

      {/* Hero Balance Card */}
      <Card variant="elevated">
        <Card.Body>
          <BalanceHero
            label="Total Shares Capital Value"
            accountNumber={user?.reg_no || 'UDS-2026'}
            amount={totalShares}
            currency="KES"
            onAddMoney={() => setBuyModal(true)}
          />
        </Card.Body>
      </Card>

      {/* Metrics Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-lime-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Coins size={20} />
            </div>
            <Badge status="active">Units</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Number of Shares Held</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatNumber(numSharesHeld)} Shares
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            At {formatKES(unitPrice)} per share par value
          </div>
        </Card>

        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-gray-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Award size={20} color="var(--color-forest)" />
            </div>
            <Badge status="approved">14.5% Yield</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Projected Annual Dividend</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-forest)', marginTop: '2px' }}>
            {formatKES(projectedDividend)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Disbursed annually after AGM approval
          </div>
        </Card>
      </div>

      {/* Dividends History */}
      <Card variant="default">
        <Card.Header>
          <div>
            <h3 className="heading-2" style={{ fontSize: '18px', margin: 0 }}>Dividend Distribution History</h3>
            <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Record of dividend payouts credited to your wallet</p>
          </div>
        </Card.Header>
        <Card.Body>
          {dividends.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {dividends.map((d: any, idx: number) => (
                <ListItem
                  key={idx}
                  icon={<Award size={18} color="#16a34a" />}
                  label={`Financial Year ${d.financial_year} Dividend`}
                  time={`Paid on ${formatDate(d.created_at)} • Held: ${formatNumber(d.shares_held)} shares`}
                  amount={`+${formatKES(d.dividend_amount)}`}
                  type="credit"
                />
              ))}
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '36px', color: 'var(--color-gray-medium)', fontSize: '14px' }}>
              No dividend distributions recorded yet. Dividends are declared annually at the AGM.
            </div>
          )}
        </Card.Body>
      </Card>

      {/* Buy Shares Modal */}
      {buyModal && (
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
          onClick={() => setBuyModal(false)}
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
                <h3 className="heading-3" style={{ margin: 0 }}>Purchase Co-op Shares</h3>
                <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Instant M-Pesa STK push</p>
              </div>
              <button
                type="button"
                onClick={() => setBuyModal(false)}
                style={{ width: '32px', height: '32px', borderRadius: '50%', border: 'none', background: 'var(--color-gray-light)', color: 'var(--color-gray-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleBuyShares} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <Input
                label="Number of Shares"
                type="number"
                min="1"
                step="1"
                value={sharesToBuy}
                onChange={(e) => setSharesToBuy(e.target.value)}
                placeholder="e.g. 10"
                helperText={`Unit Price: ${formatKES(unitPrice)} • Total: ${formatKES(totalCost)}`}
                required
              />

              <Input
                label="M-Pesa Phone Number"
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="e.g. 0712345678"
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
                {submitting ? 'Processing...' : `Pay ${formatKES(totalCost)} via M-Pesa`}
              </Button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
