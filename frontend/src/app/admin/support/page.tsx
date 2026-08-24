'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/utils';
import { Headphones, CheckCircle2, Clock } from 'lucide-react';

export default function AdminSupportPage() {
  const [tickets, setTickets] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTickets = async () => {
      try {
        const res = await api.get('/admin/dashboard');
        if (res.status === 'success') {
          setTickets(res.data.recent_tickets || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchTickets();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Member Support Helpdesk</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Review member inquiries, dispute tickets, and provide customer support</p>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Ticket ID</th>
                <th>Subject</th>
                <th>Member Sender</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Opened Date</th>
              </tr>
            </thead>
            <tbody>
              {tickets.length ? (
                tickets.map((t, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>#TK-{t.id || t.ticket_id}</td>
                    <td style={{ fontWeight: 600 }}>{t.subject}</td>
                    <td>{t.sender || 'Member'}</td>
                    <td><span className={`badge ${t.priority === 'High' ? 'badge-danger' : 'badge-info'}`}>{t.priority || 'Normal'}</span></td>
                    <td><span className={`badge ${t.status === 'Closed' ? 'badge-success' : 'badge-warning'}`}>{t.status || 'Open'}</span></td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(t.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading helpdesk tickets...' : 'No open support tickets.'}
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
