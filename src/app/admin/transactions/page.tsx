'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import { FileSpreadsheet, ArrowDownRight, ArrowUpRight, Search, Printer } from 'lucide-react';

export default function AdminTransactionsLedgerPage() {
  const [txns, setTxns] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTxns = async () => {
      try {
        const res = await api.get('/admin/live_monitor');
        if (res.status === 'success') {
          setTxns(res.data.logs || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchTxns();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>Live General Ledger</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>Master double-entry transaction record across all member and institutional accounts</p>
        </div>

        <button
          onClick={() => window.print()}
          className="btn btn-outline-forest"
          style={{ borderRadius: '50px', padding: '10px 20px' }}
        >
          <Printer size={16} /> Print Master Ledger
        </button>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Event / Action</th>
                <th>Entity Affected</th>
                <th>Admin Officer</th>
                <th>IP Address</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              {txns.length ? (
                txns.map((l, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{l.action}</td>
                    <td>{l.entity_type || 'General'} #{l.entity_id || 1}</td>
                    <td>{l.admin_name || 'System Admin'}</td>
                    <td><span style={{ fontFamily: 'monospace', fontSize: '0.82rem' }}>{l.ip_address || '127.0.0.1'}</span></td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(l.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Streaming ledger...' : 'Live ledger running.'}
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
