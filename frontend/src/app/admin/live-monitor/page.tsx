'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/utils';
import { Monitor, Activity, Radio } from 'lucide-react';

export default function AdminLiveMonitorPage() {
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchLogs = async () => {
      try {
        const res = await api.get('/admin/live_monitor');
        if (res.status === 'success') {
          setLogs(res.data.logs || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchLogs();
    const interval = setInterval(fetchLogs, 10000);
    return () => clearInterval(interval);
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Live Audit & Security Monitor</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Real-time immutable audit trail of administrative modifications, KYC actions, and logins</p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 14px', borderRadius: '50px', backgroundColor: 'rgba(34, 197, 94, 0.1)', color: '#16a34a', fontWeight: 700, fontSize: '0.82rem' }}>
          <span style={{ width: '8px', height: '8px', borderRadius: '50%', backgroundColor: '#16a34a', animation: 'pulse 1.5s infinite' }} />
          Live Polling Stream (10s)
        </div>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Event Action</th>
                <th>Target Entity</th>
                <th>Staff Officer</th>
                <th>Client IP</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              {logs.length ? (
                logs.map((l, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{l.action}</td>
                    <td>{l.entity_type} #{l.entity_id}</td>
                    <td>{l.admin_name || 'System'}</td>
                    <td><span style={{ fontFamily: 'monospace', fontSize: '0.82rem' }}>{l.ip_address || '127.0.0.1'}</span></td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(l.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Connecting to live audit stream...' : 'No audit records logged.'}
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
