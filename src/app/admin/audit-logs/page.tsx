'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/utils';
import {
  ShieldAlert,
  Search,
  Filter,
  Download,
  ShieldCheck,
  AlertTriangle,
  Info,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
} from 'lucide-react';

export default function AdminAuditLogsPage() {
  const [logs, setLogs] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>({ page: 1, pages: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [severity, setSeverity] = useState('all');
  const [page, setPage] = useState(1);

  const fetchLogs = async (p = 1) => {
    setLoading(true);
    try {
      const res = await api.get('/admin/audit_logs', {
        page: p,
        search,
        severity: severity !== 'all' ? severity : undefined,
      });
      if (res.status === 'success') {
        setLogs(res.data.logs || []);
        setPagination(res.data.pagination || { page: p, pages: 1, total: 0 });
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs(page);
  }, [page, severity]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchLogs(1);
  };

  const getSeverityBadge = (sev: string) => {
    switch (sev) {
      case 'danger':
      case 'critical':
        return <span className="badge badge-danger">Critical</span>;
      case 'warning':
        return <span className="badge badge-warning">Warning</span>;
      default:
        return <span className="badge badge-info">Info</span>;
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ── Header ── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <div className="eyebrow-pill" style={{ marginBottom: '6px' }}>
            <span className="eyebrow-dot" /> Security & Compliance Trail
          </div>
          <h1 style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
            System Activity Audit Trail
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>
            Immutable administrative access logs, loan approvals, payout records, and KYC decisions
          </p>
        </div>

        <button
          onClick={() => window.open(`/api/v1/admin/audit_logs?format=csv&search=${encodeURIComponent(search)}`, '_blank')}
          className="btn btn-outline-forest"
          style={{ borderRadius: '50px', padding: '10px 20px' }}
        >
          <Download size={16} /> Export CSV Audit Trail
        </button>
      </div>

      {/* ── Search & Filter Bar ── */}
      <div className="card" style={{ padding: '18px 24px', borderRadius: '18px' }}>
        <form onSubmit={handleSearch} style={{ display: 'flex', flexWrap: 'wrap', gap: '14px', alignItems: 'center' }}>
          <div style={{ flex: 1, minWidth: '240px', position: 'relative' }}>
            <input
              type="text"
              className="input-control"
              placeholder="Search by action, keyword, or user details..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              style={{ paddingLeft: '36px' }}
            />
            <Search size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
          </div>

          <select
            className="form-select"
            style={{ width: 'auto', minWidth: '160px' }}
            value={severity}
            onChange={(e) => setSeverity(e.target.value)}
          >
            <option value="all">All Severities</option>
            <option value="info">Info / General</option>
            <option value="warning">Warning / Flagged</option>
            <option value="danger">Critical / Sensitive</option>
          </select>

          <button type="submit" className="btn btn-forest">
            <Search size={16} /> Filter
          </button>
        </form>
      </div>

      {/* ── Audit Logs Table ── */}
      <div className="card" style={{ padding: 0, overflow: 'hidden', borderRadius: '20px' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Actor Type</th>
                <th>Action Name</th>
                <th>Severity</th>
                <th>IP Address</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    Loading audit trail...
                  </td>
                </tr>
              ) : logs.length > 0 ? (
                logs.map((log) => (
                  <tr key={log.audit_id}>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem', whiteSpace: 'nowrap' }}>
                      {formatDate(log.created_at)}
                    </td>
                    <td>
                      <span className="badge badge-forest" style={{ textTransform: 'capitalize' }}>
                        {log.user_type || 'Admin'}
                      </span>
                    </td>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace', fontSize: '0.85rem' }}>
                      {log.action}
                    </td>
                    <td>{getSeverityBadge(log.severity)}</td>
                    <td style={{ fontFamily: 'monospace', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                      {log.ip_address || '127.0.0.1'}
                    </td>
                    <td style={{ fontSize: '0.82rem', maxWidth: '320px', lineHeight: 1.4 }}>
                      {log.details || '—'}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '48px', color: 'var(--text-muted)' }}>
                    No audit records matching criteria.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {pagination.pages > 1 && (
          <div style={{ padding: '16px 24px', borderTop: '1px solid var(--border-color)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
              Page {pagination.page} of {pagination.pages} ({pagination.total} total logs)
            </span>

            <div style={{ display: 'flex', gap: '8px' }}>
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={pagination.page <= 1}
                className="btn btn-outline-forest"
                style={{ padding: '6px 12px', fontSize: '0.8rem' }}
              >
                <ChevronLeft size={14} /> Previous
              </button>
              <button
                onClick={() => setPage((p) => Math.min(pagination.pages, p + 1))}
                disabled={pagination.page >= pagination.pages}
                className="btn btn-outline-forest"
                style={{ padding: '6px 12px', fontSize: '0.8rem' }}
              >
                Next <ChevronRight size={14} />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
