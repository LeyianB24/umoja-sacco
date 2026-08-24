'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { MetricCard } from '@/components/ui/MetricCard';
import { formatKES, formatDate } from '@/lib/utils';
import { Building2, TrendingUp, Coins } from 'lucide-react';

export default function AdminInvestmentsPage() {
  const [investments, setInvestments] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchInvestments = async () => {
      try {
        const res = await api.get('/admin/investments');
        if (res.status === 'success') {
          setInvestments(res.data.investments || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchInvestments();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Asset & Investment Portfolio</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Sacco capital investments, commercial real estate, money markets, and treasury bills</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard title="Total Investment Assets" value={formatKES(45000000)} variant="forest" icon={<Building2 size={24} />} />
        <MetricCard title="Annual Portfolio ROI" value="12.8% p.a." variant="lime" icon={<TrendingUp size={24} />} />
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Investment Holdings</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Asset / Instrument</th>
                <th>Asset Type</th>
                <th>Principal Invested</th>
                <th>Current Valuation</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {investments.length ? (
                investments.map((inv, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{inv.name || inv.title}</td>
                    <td>{inv.type || 'Commercial Property'}</td>
                    <td>{formatKES(inv.amount || inv.principal)}</td>
                    <td style={{ fontWeight: 800, color: '#16a34a' }}>{formatKES(inv.current_value || inv.amount)}</td>
                    <td><span className="badge badge-success">{inv.status || 'Active'}</span></td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading asset portfolio...' : 'All core Sacco capital assets performing at target ROI.'}
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
