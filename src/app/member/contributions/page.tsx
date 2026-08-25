'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import { CalendarCheck, Filter, Download } from 'lucide-react';

export default function ContributionsPage() {
  const [contributions, setContributions] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterType, setFilterType] = useState('all');

  useEffect(() => {
    const fetchContributions = async () => {
      try {
        const res = await api.get('/member/contributions');
        if (res.status === 'success') {
          setContributions(res.data.contributions || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchContributions();
  }, []);

  const filtered = contributions.filter((c) => {
    if (filterType === 'all') return true;
    return c.type?.toLowerCase() === filterType.toLowerCase();
  });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Contributions History</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Complete record of your monthly savings, welfare, and share deposits</p>
        </div>

        <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
          <Filter size={16} style={{ color: 'var(--text-muted)' }} />
          <select
            className="input-control"
            style={{ width: 'auto', padding: '8px 14px' }}
            value={filterType}
            onChange={(e) => setFilterType(e.target.value)}
          >
            <option value="all">All Contribution Types</option>
            <option value="savings">Savings Deposits</option>
            <option value="shares">Share Purchases</option>
            <option value="welfare">Welfare Pool</option>
          </select>
        </div>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Amount Contributed</th>
                <th>Payment Method</th>
                <th>Reference Code</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {filtered.length ? (
                filtered.map((c, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, textTransform: 'capitalize' }}>
                      {c.type || 'Savings'}
                    </td>
                    <td style={{ fontWeight: 800, color: '#16a34a' }}>
                      +{formatKES(c.amount)}
                    </td>
                    <td style={{ textTransform: 'uppercase', fontSize: '0.82rem' }}>{c.method || 'M-Pesa'}</td>
                    <td><span style={{ fontFamily: 'monospace' }}>{c.reference || 'N/A'}</span></td>
                    <td>
                      <span className={`badge ${c.status === 'completed' ? 'badge-success' : 'badge-warning'}`}>
                        {c.status || 'completed'}
                      </span>
                    </td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(c.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading contributions...' : 'No contribution records found.'}
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
