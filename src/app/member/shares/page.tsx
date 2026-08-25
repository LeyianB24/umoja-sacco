'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate, formatNumber } from '@/lib/utils';
import { PieChart, TrendingUp, PlusCircle, Coins, Award } from 'lucide-react';

export default function SharesPage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [buyModal, setBuyModal] = useState(false);
  const [sharesToBuy, setSharesToBuy] = useState('10');
  const [phone, setPhone] = useState('');
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
  const totalCost = (parseInt(sharesToBuy) || 0) * unitPrice;

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
      toast.success(`M-Pesa STK Prompt for ${sharesToBuy} shares (KES ${totalCost}) sent to your phone.`);
      setBuyModal(false);
      fetchShares();
    } catch (err: any) {
      toast.error(err.message || 'Share purchase failed.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '60px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Shares Portfolio...</div>;
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Shares & Equity Portfolio</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Co-operative ownership equity and annual dividend returns</p>
        </div>
        <button onClick={() => setBuyModal(true)} className="btn btn-lime">
          <PlusCircle size={16} /> Purchase Shares
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
        <MetricCard
          title="Total Shares Value"
          value={formatKES(data?.total_shares || 0)}
          subtitle="Non-withdrawable co-op equity"
          variant="forest"
          icon={<PieChart size={24} />}
        />
        <MetricCard
          title="Number of Shares Held"
          value={formatNumber(data?.num_shares || 0)}
          subtitle={`At ${formatKES(unitPrice)} per share`}
          variant="lime"
          icon={<Coins size={24} />}
        />
        <MetricCard
          title="Projected Annual Dividend"
          value={formatKES((data?.total_shares || 0) * 0.145)}
          subtitle="Based on 14.5% avg yield"
          variant="default"
          icon={<Award size={24} color="#16a34a" />}
        />
      </div>

      {/* Dividends History */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Dividend Distribution History</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Financial Year</th>
                <th>Shares Held</th>
                <th>Dividend Paid</th>
                <th>Payment Date</th>
              </tr>
            </thead>
            <tbody>
              {data?.dividends?.length ? (
                data.dividends.map((d: any, i: number) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{d.financial_year}</td>
                    <td>{formatNumber(d.shares_held)}</td>
                    <td style={{ fontWeight: 800, color: '#16a34a' }}>+{formatKES(d.dividend_amount)}</td>
                    <td style={{ color: 'var(--text-muted)' }}>{formatDate(d.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={4} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    No dividend distributions recorded yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Buy Shares Modal */}
      <Modal isOpen={buyModal} onClose={() => setBuyModal(false)} title="Purchase Co-op Shares">
        <form onSubmit={handleBuyShares} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">Number of Shares to Purchase</label>
            <input
              type="number"
              min={1}
              step={1}
              className="input-control"
              value={sharesToBuy}
              onChange={(e) => setSharesToBuy(e.target.value)}
              required
            />
            <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '4px' }}>
              Unit Price: {formatKES(unitPrice)} • Total Amount: <b>{formatKES(totalCost)}</b>
            </div>
          </div>
          <div>
            <label className="input-label">M-Pesa Phone Number</label>
            <input
              type="text"
              className="input-control"
              placeholder="e.g. 0712345678"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={submitting} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {submitting ? 'Processing...' : `Pay ${formatKES(totalCost)} via M-Pesa`}
          </button>
        </form>
      </Modal>
    </div>
  );
}
