'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatNumber } from '@/lib/utils';
import { PieChart, Users, Coins, Award, PlusCircle, CheckCircle2 } from 'lucide-react';

export default function AdminSharesPage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Dividend modal
  const [divModal, setDivModal] = useState(false);
  const [dividendAmount, setDividendAmount] = useState('');
  const [financialYear, setFinancialYear] = useState(String(new Date().getFullYear()));
  const [distributing, setDistributing] = useState(false);

  const fetchShares = async () => {
    try {
      const res = await api.get('/admin/shares');
      if (res.status === 'success') {
        setData(res.data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchShares();
  }, []);

  const handleDeclareDividends = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(dividendAmount);
    if (isNaN(amt) || amt <= 0) return;

    setDistributing(true);
    try {
      const res = await api.post('/admin/declare_dividends', {
        dividend_amount: amt,
        financial_year: financialYear,
      });
      toast.success(res.message || 'Dividends distributed to member accounts!');
      setDivModal(false);
      setDividendAmount('');
      fetchShares();
    } catch (err: any) {
      toast.error(err.message || 'Dividend declaration failed.');
    } finally {
      setDistributing(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '80px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Shares & Equity...</div>;
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Equity Capital & Shares</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Manage Sacco share capital reserve, share pricing, and annual dividend declarations</p>
        </div>
        <button onClick={() => setDivModal(true)} className="btn btn-lime">
          <Award size={16} /> Declare & Distribute Dividends
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
        <MetricCard
          title="Total Sacco Share Capital"
          value={formatKES(data?.total_capital || 0)}
          subtitle="Non-withdrawable permanent equity"
          variant="forest"
          icon={<PieChart size={22} />}
        />
        <MetricCard
          title="Shareholders Count"
          value={formatNumber(data?.total_shareholders || 0)}
          subtitle="Active member investors"
          variant="lime"
          icon={<Users size={22} />}
        />
        <MetricCard
          title="Unit Share Price"
          value={formatKES(data?.unit_price || 100)}
          subtitle="Par valuation setting"
          variant="default"
          icon={<Coins size={22} color="#16a34a" />}
        />
      </div>

      {/* Distribution Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Member Shareholding Register</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Reg No</th>
                <th>Member Full Name</th>
                <th>Shares Count</th>
                <th>Total Value</th>
                <th>Equity Ownership %</th>
              </tr>
            </thead>
            <tbody>
              {data?.shareholders?.length ? (
                data.shareholders.map((sh: any, i: number) => {
                  const pct = data.total_capital > 0 ? ((sh.shares_balance / data.total_capital) * 100).toFixed(2) : '0.00';
                  return (
                    <tr key={i}>
                      <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>{sh.member_reg_no}</td>
                      <td style={{ fontWeight: 600 }}>{sh.full_name}</td>
                      <td>{formatNumber(sh.num_shares)}</td>
                      <td style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>{formatKES(sh.shares_balance)}</td>
                      <td><span className="badge badge-lime">{pct}%</span></td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    No shareholding records found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Declare Dividends Modal */}
      <Modal isOpen={divModal} onClose={() => setDivModal(false)} title="Declare Annual Dividends">
        <form onSubmit={handleDeclareDividends} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">Total Dividend Payout Pool (KES)</label>
            <input
              type="number"
              min={1000}
              step={100}
              className="input-control"
              placeholder="e.g. 500000"
              value={dividendAmount}
              onChange={(e) => setDividendAmount(e.target.value)}
              required
            />
            <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '4px' }}>
              Dividends will be calculated pro-rata against each shareholder's balance and credited directly to their savings/wallet.
            </div>
          </div>

          <div>
            <label className="input-label">Financial Year</label>
            <input
              type="text"
              className="input-control"
              value={financialYear}
              onChange={(e) => setFinancialYear(e.target.value)}
              required
            />
          </div>

          <button type="submit" disabled={distributing} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {distributing ? 'Distributing Pro-Rata...' : 'Confirm & Disburse Dividends'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
