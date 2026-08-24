'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { MetricCard } from '@/components/ui/MetricCard';
import { formatKES, formatDate } from '@/lib/utils';
import { Wallet2, Users, FileText, CheckCircle2 } from 'lucide-react';

export default function AdminPayrollPage() {
  const [runs, setRuns] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPayroll = async () => {
      try {
        const res = await api.get('/admin/payroll_runs');
        if (res.status === 'success') {
          setRuns(res.data.payroll_runs || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchPayroll();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Payroll Processing</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Statutory PAYE, NSSF, NHIF/SHIF, Housing Levy calculations and staff disbursements</p>
        </div>
        <Link href="/admin/employees" className="btn btn-lime">
          <Users size={16} /> Manage Employees
        </Link>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard title="Total Monthly Payroll" value={formatKES(1850000)} variant="forest" icon={<Wallet2 size={24} />} />
        <MetricCard title="Active Staff Count" value="24 Employees" variant="lime" icon={<Users size={24} />} />
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Monthly Payroll Batches</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Period</th>
                <th>Employees</th>
                <th>Gross Total</th>
                <th>Total Deductions</th>
                <th>Net Total Disbursed</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {runs.length ? (
                runs.map((r, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 800, fontFamily: 'monospace' }}>{r.period}</td>
                    <td>{r.employee_count} Staff</td>
                    <td>{formatKES(r.total_gross)}</td>
                    <td style={{ color: '#dc2626' }}>{formatKES(r.total_deductions)}</td>
                    <td style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>{formatKES(r.total_net)}</td>
                    <td><span className="badge badge-success">{r.status || 'Paid'}</span></td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading payroll records...' : 'Monthly payroll processing on schedule.'}
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
