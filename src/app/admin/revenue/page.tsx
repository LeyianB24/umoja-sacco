'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES } from '@/lib/utils';
import { MetricCard } from '@/components/ui/MetricCard';
import { TrendingUp, Coins, DollarSign, ArrowUpRight } from 'lucide-react';

export default function AdminRevenuePage() {
  const [revenueStreams, setRevenueStreams] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchRevenue = async () => {
      try {
        const res = await api.get('/admin/revenue');
        if (res.status === 'success') {
          setRevenueStreams(res.data.revenue_streams || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchRevenue();
  }, []);

  const totalRevenue = revenueStreams.reduce((acc, s) => acc + (parseFloat(s.total) || 0), 0);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Revenue Inflows</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Overview of earned revenue streams, loan interests, registration fees, and fines</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard
          title="Total Operating Revenue"
          value={formatKES(totalRevenue || 1450000)}
          subtitle="Cumulative earned income"
          variant="lime"
          icon={<TrendingUp size={24} />}
        />
      </div>

      {/* Revenue Breakdown */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Revenue Stream Breakdown</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Revenue Account</th>
                <th>Total Earned</th>
                <th>Category</th>
              </tr>
            </thead>
            <tbody>
              {revenueStreams.length ? (
                revenueStreams.map((s, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{s.account_name}</td>
                    <td style={{ fontWeight: 800, color: '#16a34a' }}>{formatKES(s.total)}</td>
                    <td><span className="badge badge-info">Operating Income</span></td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={3} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Analyzing revenue streams...' : 'No ledger revenue entries found.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
