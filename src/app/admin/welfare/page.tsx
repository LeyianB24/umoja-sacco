'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { formatKES, formatDate } from '@/lib/utils';
import { HeartPulse, ShieldCheck, CheckCircle2, XCircle } from 'lucide-react';

export default function AdminWelfarePage() {
  const { toast } = useToast();
  const [claims, setClaims] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchClaims = async () => {
    try {
      const res = await api.get('/admin/welfare');
      if (res.status === 'success') {
        setClaims(res.data.claims || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchClaims();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Welfare & Solidarity Fund Oversight</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Review and manage member emergency aid, medical, and bereavement solidarity claims</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard title="Welfare Pool Reserve" value={formatKES(8500000)} variant="forest" icon={<HeartPulse size={24} />} />
        <MetricCard title="Settled Claims This Year" value={formatKES(1240000)} variant="lime" icon={<ShieldCheck size={24} />} />
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Member Welfare Aid Requests</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Member Reg No</th>
                <th>Claim Type</th>
                <th>Claim Amount</th>
                <th>Details</th>
                <th>Status</th>
                <th>Submitted On</th>
              </tr>
            </thead>
            <tbody>
              {claims.length ? (
                claims.map((c, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>{c.member_reg_no || `MEM-${c.member_id}`}</td>
                    <td>{c.claim_type}</td>
                    <td style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>{formatKES(c.amount)}</td>
                    <td style={{ color: 'var(--text-muted)', maxWidth: '300px' }}>{c.details || '-'}</td>
                    <td>
                      <span className={`badge ${c.status === 'approved' ? 'badge-success' : 'badge-warning'}`}>
                        {c.status}
                      </span>
                    </td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(c.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    No pending welfare claims in queue.
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
