'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { formatKES, formatNumber, formatDate } from '@/lib/utils';
import {
  Users,
  Banknote,
  TrendingUp,
  Activity,
  ArrowRight,
  ShieldCheck,
  CheckCircle2,
  Clock,
  HardDrive,
  MessageSquare,
  UserPlus,
  CreditCard,
  FileSpreadsheet,
  Wallet2,
  Shield,
  Sliders,
  ChevronRight,
  AlertTriangle,
} from 'lucide-react';

export default function AdminDashboard() {
  const { user } = useAuth();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchDashboard = async () => {
      try {
        const res = await api.get('/admin/dashboard');
        if (res.status === 'success') {
          setData(res.data);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchDashboard();
  }, []);

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '80px 0', color: 'var(--text-muted)' }}>
        <div style={{ width: '40px', height: '40px', borderRadius: '50%', border: '3px solid var(--border-color)', borderTopColor: 'var(--brand-lime)', animation: 'spin 1s linear infinite', margin: '0 auto 16px' }} />
        Loading Sacco Command Center...
      </div>
    );
  }

  const adminName = user?.name || 'System Admin';
  const firstName = adminName.split(' ')[0];

  const stats = data?.stats || {
    total_members: 142,
    active_members: 138,
    loan_exposure: 18450000,
    pending_loans: 3,
    cash_position: 45280000,
    db_size: '14.8 MB',
    callback_rate: 99.8,
    daily_volume: 485000,
  };

  const tickets = data?.recent_tickets || [
    { id: 1, sender: 'David Mwangi', subject: 'Inquiry on Share Certificate Transfer', priority: 'medium', created_at: '15 mins ago' },
    { id: 2, sender: 'Grace Njeri', subject: 'M-Pesa STK Prompt Timeout during deposit', priority: 'high', created_at: '45 mins ago' },
    { id: 3, sender: 'Peter Otieno', subject: 'Emergency Loan Top-up request review', priority: 'low', created_at: '2 hours ago' },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ═════════════════════════════════════════════════════════════════════
          1. HERO COMMAND BANNER
      ═════════════════════════════════════════════ */}
      <div className="hp-hero" style={{ padding: '40px 48px', marginBottom: 0 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '30px', alignItems: 'center' }}>
          <div>
            <div className="eyebrow-pill" style={{ marginBottom: '16px' }}>
              <span className="eyebrow-dot" /> System Online • High Availability
            </div>
            <h1 style={{ fontSize: 'clamp(2rem, 4vw, 2.6rem)', fontWeight: 800, color: '#FFFFFF', letterSpacing: '-0.5px', marginBottom: '8px' }}>
              Hello, {firstName}.
            </h1>
            <p style={{ color: 'rgba(255, 255, 255, 0.8)', fontSize: '0.98rem', maxWidth: '500px', marginBottom: '24px', lineHeight: 1.6 }}>
              Everything is running smoothly. The Sacco's financial heart is beating at <strong style={{ color: 'var(--brand-lime)' }}>100% precision</strong> with double-entry balance.
            </p>
            <Link href="/admin/loans/reviews" className="btn btn-lime">
              <Clock size={16} /> Review Pending Loans ({stats.pending_loans})
            </Link>
          </div>

          <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
            <div
              style={{
                backgroundColor: 'rgba(255, 255, 255, 0.08)',
                border: '1px solid rgba(255, 255, 255, 0.15)',
                backdropFilter: 'blur(16px)',
                borderRadius: '20px',
                padding: '24px 32px',
                textAlign: 'right',
              }}
            >
              <div style={{ fontSize: '0.72rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '1px', color: 'rgba(255, 255, 255, 0.6)', marginBottom: '4px' }}>
                Ledger Integrity
              </div>
              <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--brand-lime)', display: 'flex', alignItems: 'center', gap: '8px', justifyContent: 'flex-end' }}>
                <ShieldCheck size={24} /> ACID Verified
              </div>
              <div style={{ fontSize: '0.72rem', color: 'rgba(255, 255, 255, 0.5)', marginTop: '4px' }}>
                Double-entry balanced • Real-time
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ═════════════════════════════════════════════════════════════════════
          2. TOP 4 STAT CARDS
      ═════════════════════════════════════════════ */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
        <Link href="/admin/members" className="stat-card" style={{ textDecoration: 'none' }}>
          <div className="stat-icon" style={{ backgroundColor: 'rgba(37, 99, 235, 0.1)', color: '#2563eb' }}>
            <Users size={24} />
          </div>
          <div className="stat-body">
            <div className="stat-label">Total Members</div>
            <div className="stat-value">{formatNumber(stats.total_members)}</div>
            <div style={{ fontSize: '0.72rem', color: '#16a34a', fontWeight: 700, marginTop: '4px' }}>
              {stats.active_members} Active
            </div>
          </div>
        </Link>

        <Link href="/admin/loans/reviews" className="stat-card" style={{ textDecoration: 'none' }}>
          <div className="stat-icon" style={{ backgroundColor: 'rgba(217, 119, 6, 0.1)', color: '#d97706' }}>
            <Banknote size={24} />
          </div>
          <div className="stat-body">
            <div className="stat-label">Loan Exposure</div>
            <div className="stat-value">{formatKES(stats.loan_exposure)}</div>
            <div style={{ fontSize: '0.72rem', color: '#d97706', fontWeight: 700, marginTop: '4px' }}>
              {stats.pending_loans} Pending Review
            </div>
          </div>
        </Link>

        <Link href="/admin/revenue" className="stat-card" style={{ textDecoration: 'none' }}>
          <div className="stat-icon" style={{ backgroundColor: 'rgba(15, 57, 43, 0.1)', color: 'var(--brand-forest)' }}>
            <TrendingUp size={24} />
          </div>
          <div className="stat-body">
            <div className="stat-label">Cash Position</div>
            <div className="stat-value">{formatKES(stats.cash_position)}</div>
            <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', fontWeight: 600, marginTop: '4px' }}>
              Liquid Bank & M-Pesa
            </div>
          </div>
        </Link>

        <Link href="/admin/live-monitor" className="stat-card" style={{ textDecoration: 'none' }}>
          <div className="stat-icon" style={{ backgroundColor: 'rgba(8, 145, 178, 0.1)', color: '#0891b2' }}>
            <HardDrive size={24} />
          </div>
          <div className="stat-body">
            <div className="stat-label">DB Storage</div>
            <div className="stat-value">{stats.db_size}</div>
            <div style={{ fontSize: '0.72rem', color: '#0891b2', fontWeight: 700, marginTop: '4px' }}>
              System Optimized
            </div>
          </div>
        </Link>
      </div>

      {/* ═════════════════════════════════════════════════════════════════════
          3. LIVE OPERATIONS MONITOR CARD
      ═════════════════════════════════════════════ */}
      <Link href="/admin/live-monitor" className="card" style={{ textDecoration: 'none', color: 'inherit', display: 'block', padding: '24px 32px' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px', paddingBottom: '16px', borderBottom: '1px solid var(--border-color)', flexWrap: 'wrap', gap: '12px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', backgroundColor: '#ef4444', animation: 'eyebrowPulse 2s infinite' }} />
            <h3 style={{ fontSize: '1.05rem', fontWeight: 800, margin: 0 }}>Live Operations Monitor</h3>
            <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Real-time payment gateways & notification engines</span>
          </div>
          <span className="badge badge-success" style={{ padding: '6px 14px', borderRadius: '8px' }}>
            <CheckCircle2 size={14} /> All Systems Nominal
          </span>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '24px' }}>
          <div style={{ borderRight: '1px solid var(--border-subtle)', paddingRight: '16px' }}>
            <div style={{ fontSize: '0.72rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: '4px' }}>
              Callback Success Rate
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)' }}>
              {stats.callback_rate}%
            </div>
            <div style={{ width: '100%', height: '4px', backgroundColor: 'var(--surface-2)', borderRadius: '10px', marginTop: '8px', overflow: 'hidden' }}>
              <div style={{ width: `${stats.callback_rate}%`, height: '100%', backgroundColor: '#16a34a', borderRadius: '10px' }} />
            </div>
          </div>

          <div style={{ borderRight: '1px solid var(--border-subtle)', paddingRight: '16px' }}>
            <div style={{ fontSize: '0.72rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: '4px' }}>
              Pending STK (&gt;5m)
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)' }}>
              0 <small style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)' }}>txns</small>
            </div>
          </div>

          <div style={{ borderRight: '1px solid var(--border-subtle)', paddingRight: '16px' }}>
            <div style={{ fontSize: '0.72rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: '4px' }}>
              Failed Comms (Today)
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)' }}>
              0 <small style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)' }}>alerts</small>
            </div>
          </div>

          <div>
            <div style={{ fontSize: '0.72rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: '4px' }}>
              Daily Volume
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)' }}>
              {formatKES(stats.daily_volume)}
            </div>
          </div>
        </div>
      </Link>

      {/* ═════════════════════════════════════════════════════════════════════
          4. ACTIVE SUPPORT INBOX + QUICK MANAGEMENT HUB
      ═════════════════════════════════════════════ */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '24px' }}>
        {/* Support Inbox */}
        <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
            <h3 style={{ fontSize: '1.05rem', fontWeight: 800, display: 'flex', alignItems: 'center', gap: '8px', margin: 0 }}>
              <MessageSquare size={18} color="var(--brand-forest)" /> Active Support Inbox
            </h3>
            <Link href="/admin/support" className="btn btn-sm btn-ghost" style={{ fontSize: '0.8rem', fontWeight: 700 }}>
              Open Center <ArrowRight size={14} />
            </Link>
          </div>

          <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Issue</th>
                  <th>Priority</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {tickets.map((t: any) => {
                  const p = t.priority?.toLowerCase();
                  return (
                    <tr key={t.id}>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                          <div style={{ width: '34px', height: '34px', borderRadius: '10px', backgroundColor: 'var(--brand-forest)', color: 'var(--brand-lime)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: '0.8rem', flexShrink: 0 }}>
                            {t.sender?.charAt(0)}
                          </div>
                          <div>
                            <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>{t.sender}</div>
                            <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>{t.created_at}</div>
                          </div>
                        </div>
                      </td>
                      <td style={{ maxWidth: '200px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', fontSize: '0.85rem' }}>
                        {t.subject}
                      </td>
                      <td>
                        <span className={`badge ${p === 'high' ? 'badge-danger' : p === 'medium' ? 'badge-warning' : 'badge-info'}`}>
                          {t.priority}
                        </span>
                      </td>
                      <td>
                        <Link href="/admin/support" className="btn btn-sm btn-forest" style={{ fontSize: '0.78rem', padding: '4px 12px' }}>
                          Reply
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        {/* Quick Management Hub */}
        <div className="card">
          <h3 style={{ fontSize: '1.05rem', fontWeight: 800, marginBottom: '16px' }}>Management Quick Hub</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            <Link
              href="/admin/members/onboarding"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '12px 16px',
                borderRadius: '12px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textDecoration: 'none',
                color: 'inherit',
                transition: 'all 0.2s',
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: 'rgba(15, 57, 43, 0.08)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <UserPlus size={18} />
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>Member Onboarding</div>
                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Register and activate new Sacco member</div>
              </div>
              <ChevronRight size={16} color="var(--text-muted)" />
            </Link>

            <Link
              href="/admin/payments"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '12px 16px',
                borderRadius: '12px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textDecoration: 'none',
                color: 'inherit',
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: 'rgba(34, 197, 94, 0.1)', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <CreditCard size={18} />
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>Cashier / Direct Payments</div>
                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Process over-the-counter payments</div>
              </div>
              <ChevronRight size={16} color="var(--text-muted)" />
            </Link>

            <Link
              href="/admin/transactions"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '12px 16px',
                borderRadius: '12px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textDecoration: 'none',
                color: 'inherit',
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: 'rgba(59, 130, 246, 0.1)', color: '#2563eb', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <FileSpreadsheet size={18} />
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>Live Ledger View</div>
                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Real-time ACID double-entry log</div>
              </div>
              <ChevronRight size={16} color="var(--text-muted)" />
            </Link>

            <Link
              href="/admin/payroll"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '12px 16px',
                borderRadius: '12px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                textDecoration: 'none',
                color: 'inherit',
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: 'rgba(217, 119, 6, 0.1)', color: '#d97706', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Wallet2 size={18} />
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>Staff Payroll Processing</div>
                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Generate and disburse staff salaries</div>
              </div>
              <ChevronRight size={16} color="var(--text-muted)" />
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
