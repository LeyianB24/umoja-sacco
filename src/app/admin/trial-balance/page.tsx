'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES } from '@/lib/utils';
import { Scale, CheckCircle2, AlertTriangle, ShieldCheck, Printer } from 'lucide-react';

export default function AdminTrialBalancePage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTrialBalance = async () => {
      try {
        const res = await api.get('/admin/trial_balance');
        if (res.status === 'success') {
          setData(res.data);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchTrialBalance();
  }, []);

  const totals = data?.totals || { debit: 0, credit: 0, is_balanced: true };
  const accounts = data?.accounts || [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Double-Entry Trial Balance</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Verification of financial integrity across double-entry ledger accounts</p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
          <button
            onClick={() => window.print()}
            className="btn btn-outline-forest"
            style={{ borderRadius: '50px', padding: '8px 18px' }}
          >
            <Printer size={16} /> Print Trial Balance
          </button>

          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              padding: '8px 18px',
              borderRadius: '50px',
              backgroundColor: totals.is_balanced ? 'rgba(34, 197, 94, 0.12)' : 'rgba(239, 68, 68, 0.12)',
              border: `1px solid ${totals.is_balanced ? 'rgba(34, 197, 94, 0.25)' : 'rgba(239, 68, 68, 0.25)'}`,
              color: totals.is_balanced ? '#16a34a' : '#dc2626',
              fontWeight: 700,
              fontSize: '0.88rem',
            }}
          >
            {totals.is_balanced ? <CheckCircle2 size={18} /> : <AlertTriangle size={18} />}
            {totals.is_balanced ? 'Trial Balance Reconciled (Debit = Credit)' : 'Discrepancy Detected'}
          </div>
        </div>
      </div>

      {/* Trial Balance Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th>Account Classification</th>
                <th style={{ textAlign: 'right' }}>Debit Total (KES)</th>
                <th style={{ textAlign: 'right' }}>Credit Total (KES)</th>
              </tr>
            </thead>
            <tbody>
              {accounts.length ? (
                accounts.map((acc: any, i: number) => (
                  <tr key={i}>
                    <td>
                      <span style={{ fontFamily: 'monospace', fontWeight: 700 }}>
                        {acc.account_number || `ACC-${acc.account_id}`}
                      </span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{acc.account_name}</td>
                    <td>
                      <span className="badge badge-info" style={{ textTransform: 'capitalize' }}>
                        {acc.account_type}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right', fontWeight: acc.total_debit > 0 ? 700 : 400 }}>
                      {acc.total_debit > 0 ? formatKES(acc.total_debit) : '-'}
                    </td>
                    <td style={{ textAlign: 'right', fontWeight: acc.total_credit > 0 ? 700 : 400 }}>
                      {acc.total_credit > 0 ? formatKES(acc.total_credit) : '-'}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Reconciling ledger accounts...' : 'No ledger accounts found.'}
                  </td>
                </tr>
              )}
            </tbody>
            <tfoot>
              <tr style={{ backgroundColor: 'var(--surface-2)', borderTop: '2px solid var(--border-color)' }}>
                <td colSpan={3} style={{ fontWeight: 800, fontSize: '1rem' }}>Total Reconciled Balance</td>
                <td style={{ textAlign: 'right', fontWeight: 800, fontSize: '1.05rem', color: 'var(--brand-forest)' }}>
                  {formatKES(totals.debit)}
                </td>
                <td style={{ textAlign: 'right', fontWeight: 800, fontSize: '1.05rem', color: 'var(--brand-forest)' }}>
                  {formatKES(totals.credit)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  );
}
