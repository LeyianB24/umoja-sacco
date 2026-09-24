'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatDate } from '@/lib/utils';
import {
  Send,
  Mail,
  Users,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Sparkles,
  ShieldCheck,
  RefreshCw,
} from 'lucide-react';

export default function AdminBroadcastPage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>({
    stats: { totalQueued: 0, totalSent: 0, totalFailed: 0 },
    recentBroadcasts: [],
  });
  const [loading, setLoading] = useState(true);

  // Form State
  const [segment, setSegment] = useState('all');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [sending, setSending] = useState(false);

  const fetchLogs = async () => {
    try {
      const res = await api.get('/admin/broadcast');
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
    fetchLogs();
  }, []);

  const handleSendBroadcast = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!subject.trim() || !message.trim()) {
      toast.error('Please enter both subject and message.');
      return;
    }

    setSending(true);
    try {
      const res = await api.post('/admin/broadcast', {
        segment,
        subject,
        message,
      });

      toast.success(res.message || 'Broadcast queued successfully!');
      setSubject('');
      setMessage('');
      fetchLogs();
    } catch (err: any) {
      toast.error(err.message || 'Failed to dispatch broadcast.');
    } finally {
      setSending(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ── Header ── */}
      <div>
        <div className="eyebrow-pill" style={{ marginBottom: '6px' }}>
          <span className="eyebrow-dot" /> Communications Dispatch
        </div>
        <h1 style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
          Admin Email Broadcast Engine
        </h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>
          Compose and dispatch official email announcements to all SACCO members or targeted segments
        </p>
      </div>

      {/* ── Status Metrics ── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <div className="card card-hover" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <span style={{ fontSize: '0.78rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)' }}>
              Delivered Successfully
            </span>
            <div style={{ fontSize: '1.8rem', fontWeight: 800, color: '#16a34a', marginTop: '4px' }}>
              {data.stats.totalSent}
            </div>
          </div>
          <CheckCircle2 size={32} color="#16a34a" />
        </div>

        <div className="card card-hover" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <span style={{ fontSize: '0.78rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)' }}>
              Queued for Dispatch
            </span>
            <div style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--brand-forest)', marginTop: '4px' }}>
              {data.stats.totalQueued}
            </div>
          </div>
          <Clock size={32} color="var(--brand-forest)" />
        </div>

        <div className="card card-hover" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <span style={{ fontSize: '0.78rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)' }}>
              Failed / Retrying
            </span>
            <div style={{ fontSize: '1.8rem', fontWeight: 800, color: data.stats.totalFailed > 0 ? '#dc2626' : 'var(--text-muted)', marginTop: '4px' }}>
              {data.stats.totalFailed}
            </div>
          </div>
          <AlertTriangle size={32} color={data.stats.totalFailed > 0 ? '#dc2626' : '#999999'} />
        </div>
      </div>

      {/* ── Main Composer & History Layout ── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(360px, 1fr))', gap: '24px' }}>
        {/* Composer Form */}
        <div className="card" style={{ padding: '28px', borderRadius: '20px' }}>
          <h3 style={{ fontSize: '1.15rem', fontWeight: 800, marginBottom: '16px' }}>
            Compose Member Broadcast
          </h3>

          <form onSubmit={handleSendBroadcast} style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
            <div>
              <label className="input-label">Target Audience Segment</label>
              <select className="form-select" value={segment} onChange={(e) => setSegment(e.target.value)}>
                <option value="all">All Registered Members (Active & Pending)</option>
                <option value="active">Active Verified Members Only</option>
                <option value="pending">Pending KYC Registrants Only</option>
              </select>
            </div>

            <div>
              <label className="input-label">Email Subject Line <span style={{ color: '#dc2626' }}>*</span></label>
              <input
                type="text"
                className="input-control"
                placeholder="e.g. Annual General Meeting (AGM) Notice & Dividend Update"
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
                required
              />
            </div>

            <div>
              <label className="input-label">Announcement Body <span style={{ color: '#dc2626' }}>*</span></label>
              <textarea
                className="input-control"
                placeholder="Write your announcement details here..."
                rows={6}
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                required
              />
            </div>

            <button type="submit" disabled={sending} className="btn btn-forest btn-lg" style={{ width: '100%' }}>
              {sending ? 'Queueing Broadcast...' : 'Dispatch Broadcast to Email Queue'}
              {!sending && <Send size={16} />}
            </button>
          </form>
        </div>

        {/* Broadcast Queue & Activity Table */}
        <div className="card" style={{ padding: 0, overflow: 'hidden', borderRadius: '20px' }}>
          <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3 style={{ fontSize: '1.05rem', fontWeight: 800, margin: 0 }}>Recent Broadcast Logs</h3>
            <button onClick={fetchLogs} className="btn btn-outline-forest" style={{ padding: '4px 10px', fontSize: '0.75rem' }}>
              <RefreshCw size={12} /> Refresh
            </button>
          </div>

          <div className="table-container" style={{ border: 'none', borderRadius: 0, maxHeight: '420px', overflowY: 'auto' }}>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Recipient</th>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Queued Date</th>
                </tr>
              </thead>
              <tbody>
                {data.recentBroadcasts.length > 0 ? (
                  data.recentBroadcasts.map((b: any) => (
                    <tr key={b.queue_id}>
                      <td>
                        <div style={{ fontWeight: 600, fontSize: '0.85rem' }}>{b.recipient_name}</div>
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{b.recipient_email}</div>
                      </td>
                      <td style={{ fontSize: '0.82rem', maxWidth: '200px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {b.subject}
                      </td>
                      <td>
                        <span className={`badge ${b.status === 'sent' ? 'badge-success' : b.status === 'failed' ? 'badge-danger' : 'badge-warning'}`}>
                          {b.status}
                        </span>
                      </td>
                      <td style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>
                        {formatDate(b.created_at)}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={4} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                      No recent broadcast logs found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
