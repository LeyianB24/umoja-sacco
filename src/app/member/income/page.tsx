'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate } from '@/lib/utils';
import {
  TrendingUp,
  Plus,
  Calendar,
  Wallet,
  Car,
  Package,
  Bike,
  Sparkles,
  Trash2,
  Download,
  Search,
  Filter,
  ArrowUpRight,
  ShieldCheck,
  CheckCircle2,
  X,
  PieChart,
} from 'lucide-react';

export default function DailyIncomeTrackingPage() {
  const { toast } = useToast();
  const [summary, setSummary] = useState<any>({
    todaysIncome: 0,
    thisWeek: 0,
    thisMonth: 0,
    last7Days: [],
  });
  const [entries, setEntries] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  // Modal State
  const [modalOpen, setModalOpen] = useState(false);
  const [incomeDate, setIncomeDate] = useState(new Date().toISOString().split('T')[0]);
  const [amount, setAmount] = useState('');
  const [source, setSource] = useState('taxi_fares');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchSummary = async () => {
    try {
      const res = await api.get('/member/income', { summary: true });
      if (res.status === 'success') {
        setSummary(res.data);
      }
    } catch (err) {
      console.error(err);
    }
  };

  const fetchEntries = async () => {
    setLoading(true);
    try {
      const res = await api.get('/member/income', {
        filter,
        search,
        startDate: filter === 'custom' ? startDate : undefined,
        endDate: filter === 'custom' ? endDate : undefined,
      });
      if (res.status === 'success') {
        setEntries(res.data.data || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSummary();
  }, []);

  useEffect(() => {
    fetchEntries();
  }, [filter, startDate, endDate]);

  const handleRecordIncome = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) {
      setFormError('Please enter a valid amount greater than KES 0.');
      return;
    }
    if (amt > 100000) {
      setFormError('Single daily income cannot exceed KES 100,000.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await api.post('/member/income', {
        date: incomeDate,
        amount: amt,
        source,
        notes,
      });

      toast.success(res.message || `Income recorded: KES ${amt.toLocaleString()}`);
      setModalOpen(false);
      setAmount('');
      setNotes('');
      fetchSummary();
      fetchEntries();
    } catch (err: any) {
      setFormError(err.message || 'Failed to record income.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (incomeId: number) => {
    if (!window.confirm('Are you sure you want to delete this income entry? Auto-credited savings and contributions will be reversed.')) {
      return;
    }

    try {
      await api.delete(`/member/income?id=${incomeId}`);
      toast.success('Income entry removed and auto-transactions reversed.');
      fetchSummary();
      fetchEntries();
    } catch (err: any) {
      toast.error(err.message || 'Failed to delete income entry.');
    }
  };

  const getSourceIcon = (src: string) => {
    switch (src) {
      case 'taxi_fares':
        return <Car size={18} color="#0B2419" />;
      case 'delivery':
        return <Bike size={18} color="#0B2419" />;
      case 'parcel':
        return <Package size={18} color="#0B2419" />;
      default:
        return <Wallet size={18} color="#0B2419" />;
    }
  };

  // Find maximum in 7-day chart for proportional bar scaling
  const maxDayAmount = Math.max(1, ...(summary.last7Days?.map((d: any) => d.amount) || [1000]));

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ── Page Header & Quick Actions ── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <div className="eyebrow-pill" style={{ marginBottom: '6px' }}>
            <span className="eyebrow-dot" /> Driver Daily Ledger
          </div>
          <h1 style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
            Daily Income & Savings Automation
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>
            Log daily trip fares to build your credit score and automate 10% mandatory savings
          </p>
        </div>

        <button
          onClick={() => setModalOpen(true)}
          className="btn btn-forest btn-lg"
          style={{ borderRadius: '50px', padding: '12px 24px', boxShadow: '0 4px 14px rgba(11, 36, 25, 0.25)' }}
        >
          <Plus size={18} /> Record Daily Income
        </button>
      </div>

      {/* ── Top Metric Cards & Chart Grid ── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
        {/* Today */}
        <div className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.8rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px' }}>
                Today's Earnings
              </span>
              <span className="badge badge-success">Live</span>
            </div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--brand-forest)', letterSpacing: '-0.5px' }}>
              {formatKES(summary.todaysIncome)}
            </div>
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '12px', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <Sparkles size={14} color="#16a34a" /> Auto Saved: <strong style={{ color: '#16a34a' }}>{formatKES(summary.todaysIncome * 0.1)}</strong>
          </div>
        </div>

        {/* This Week */}
        <div className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.8rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px' }}>
                This Week (Mon-Sun)
              </span>
              <TrendingUp size={18} color="var(--brand-forest)" />
            </div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
              {formatKES(summary.thisWeek)}
            </div>
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '12px' }}>
            Weekly Savings Contribution: <strong style={{ color: 'var(--brand-forest)' }}>{formatKES(summary.thisWeek * 0.1)}</strong>
          </div>
        </div>

        {/* This Month */}
        <div className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.8rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px' }}>
                This Month's Total
              </span>
              <Calendar size={18} color="var(--brand-forest)" />
            </div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
              {formatKES(summary.thisMonth)}
            </div>
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '12px' }}>
            Monthly Loan Credit Power: <strong style={{ color: 'var(--brand-forest)' }}>{formatKES(summary.thisMonth * 3)}</strong>
          </div>
        </div>
      </div>

      {/* ── 7-Day Visual Earnings Chart ── */}
      <div className="card" style={{ padding: '24px 28px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
          <div>
            <h3 style={{ fontSize: '1.1rem', fontWeight: 800, margin: 0 }}>Last 7 Days Revenue Trend</h3>
            <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', margin: '2px 0 0' }}>Daily earnings in Kenyan Shillings</p>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.78rem', fontWeight: 600 }}>
            <span style={{ width: '12px', height: '12px', borderRadius: '3px', backgroundColor: 'var(--brand-forest)', display: 'inline-block' }} />
            <span>Fares Recorded</span>
          </div>
        </div>

        {/* Visual Bar Chart */}
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', height: '180px', paddingTop: '20px', gap: '12px' }}>
          {summary.last7Days?.map((d: any, i: number) => {
            const heightPercent = Math.max(8, (d.amount / maxDayAmount) * 100);
            return (
              <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px', height: '100%', justifyContent: 'flex-end' }}>
                <div style={{ fontSize: '0.72rem', fontWeight: 700, color: 'var(--text-muted)' }}>
                  {d.amount > 0 ? `${(d.amount / 1000).toFixed(1)}k` : '0'}
                </div>
                <div
                  title={`${d.date}: ${formatKES(d.amount)}`}
                  style={{
                    width: '100%',
                    maxWidth: '44px',
                    height: `${heightPercent}%`,
                    backgroundColor: d.amount > 0 ? 'var(--brand-forest)' : 'var(--border-color)',
                    background: d.amount > 0 ? 'linear-gradient(180deg, #a3e635 0%, #0b2419 100%)' : undefined,
                    borderRadius: '8px 8px 3px 3px',
                    transition: 'height 0.4s ease',
                    cursor: 'pointer',
                  }}
                />
                <span style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--text-main)' }}>{d.day}</span>
              </div>
            );
          })}
        </div>
      </div>

      {/* ── Income History List with Filters ── */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        {/* Controls Bar */}
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
          {/* Filter Chips */}
          <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            {[
              { id: 'all', label: 'All Entries' },
              { id: 'today', label: 'Today' },
              { id: 'week', label: 'This Week' },
              { id: 'month', label: 'This Month' },
            ].map((f) => (
              <button
                key={f.id}
                onClick={() => setFilter(f.id)}
                className={`btn ${filter === f.id ? 'btn-forest' : 'btn-outline-forest'}`}
                style={{ fontSize: '0.8rem', padding: '6px 14px', borderRadius: '50px' }}
              >
                {f.label}
              </button>
            ))}
          </div>

          {/* Search & Export */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <div style={{ position: 'relative' }}>
              <input
                type="text"
                className="input-control"
                placeholder="Search trip notes or source..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && fetchEntries()}
                style={{ paddingLeft: '32px', height: '38px', fontSize: '0.82rem', width: '220px' }}
              />
              <Search size={14} style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
            </div>

            <button
              onClick={() => window.open(`/api/v1/reports/export?type=income&format=xlsx`, '_blank')}
              className="btn btn-outline-forest"
              style={{ fontSize: '0.8rem', height: '38px' }}
            >
              <Download size={14} /> Export to Excel
            </button>
          </div>
        </div>

        {/* History Table */}
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Revenue Source</th>
                <th>Amount Earned</th>
                <th>Auto-Savings (10%)</th>
                <th>Daily Contribution</th>
                <th>Trip Notes</th>
                <th style={{ textAlign: 'right' }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    Loading income records...
                  </td>
                </tr>
              ) : entries.length > 0 ? (
                entries.map((entry) => (
                  <tr key={entry.income_id}>
                    <td style={{ fontWeight: 600 }}>{formatDate(entry.date)}</td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <div style={{ width: '28px', height: '28px', borderRadius: '8px', backgroundColor: 'var(--surface-2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                          {getSourceIcon(entry.source)}
                        </div>
                        <span style={{ textTransform: 'capitalize', fontWeight: 600 }}>
                          {entry.source.replace(/_/g, ' ')}
                        </span>
                      </div>
                    </td>
                    <td style={{ fontWeight: 800, fontSize: '0.98rem', color: 'var(--brand-forest)' }}>
                      {formatKES(entry.amount)}
                    </td>
                    <td style={{ fontWeight: 700, color: '#16a34a' }}>
                      +{formatKES(Number(entry.amount) * 0.1)}
                    </td>
                    <td style={{ fontWeight: 700, color: 'var(--brand-forest)' }}>
                      +{formatKES(Math.max(50, Number(entry.amount) * 0.02))}
                    </td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem', maxWidth: '240px' }}>
                      {entry.notes || '—'}
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <button
                        onClick={() => handleDelete(entry.income_id)}
                        title="Delete entry & reverse auto-savings"
                        style={{
                          background: 'none',
                          border: 'none',
                          cursor: 'pointer',
                          color: '#dc2626',
                          padding: '6px',
                          borderRadius: '6px',
                        }}
                      >
                        <Trash2 size={16} />
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '48px', color: 'var(--text-muted)' }}>
                    No income records logged yet. Click <strong>Record Daily Income</strong> to start building your savings!
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* ── Record Daily Income Modal ── */}
      {modalOpen && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0,0,0,0.65)',
            backdropFilter: 'blur(4px)',
            zIndex: 1100,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
        >
          <div
            className="card"
            style={{
              width: '100%',
              maxWidth: '440px',
              padding: '28px',
              borderRadius: '24px',
              backgroundColor: 'var(--bg-surface)',
              boxShadow: '0 25px 50px rgba(0,0,0,0.3)',
            }}
          >
            {/* Modal Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
                  Record Daily Income
                </h3>
                <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '2px 0 0' }}>
                  Trip fares, delivery revenues, or logistics income
                </p>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
              >
                <X size={20} />
              </button>
            </div>

            {formError && (
              <div
                style={{
                  padding: '10px 14px',
                  borderRadius: '10px',
                  backgroundColor: 'rgba(239, 68, 68, 0.1)',
                  color: '#dc2626',
                  fontSize: '0.82rem',
                  fontWeight: 600,
                  marginBottom: '16px',
                }}
              >
                {formError}
              </div>
            )}

            <form onSubmit={handleRecordIncome} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <div>
                <label className="input-label">Income Date</label>
                <input
                  type="date"
                  className="input-control"
                  value={incomeDate}
                  max={new Date().toISOString().split('T')[0]}
                  onChange={(e) => setIncomeDate(e.target.value)}
                  required
                />
              </div>

              <div>
                <label className="input-label">Earned Amount (KES) <span style={{ color: '#dc2626' }}>*</span></label>
                <div style={{ position: 'relative' }}>
                  <span style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', fontWeight: 800, color: 'var(--text-muted)' }}>
                    KES
                  </span>
                  <input
                    type="number"
                    min="1"
                    max="100000"
                    className="input-control"
                    placeholder="e.g. 2,500"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    style={{ paddingLeft: '56px', fontSize: '1.1rem', fontWeight: 700 }}
                    required
                    autoFocus
                  />
                </div>
              </div>

              <div>
                <label className="input-label">Revenue Source</label>
                <select className="form-select" value={source} onChange={(e) => setSource(e.target.value)}>
                  <option value="taxi_fares">Taxi / Ride-Hailing Fares</option>
                  <option value="delivery">Boda / Parcel Delivery</option>
                  <option value="parcel">Courier / Cargo Delivery</option>
                  <option value="other">Other Transport Revenue</option>
                </select>
              </div>

              <div>
                <label className="input-label">Notes (Optional)</label>
                <textarea
                  className="input-control"
                  placeholder="e.g. 120km covered, 14 passengers (Nairobi-Thika Road)"
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  rows={2}
                  style={{ resize: 'none' }}
                />
              </div>

              {/* Live Auto-Savings Calculation Preview */}
              {amount && !isNaN(parseFloat(amount)) && parseFloat(amount) > 0 && (
                <div style={{ padding: '12px 14px', borderRadius: '12px', backgroundColor: 'var(--surface-2)', fontSize: '0.82rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                    <span style={{ color: 'var(--text-muted)' }}>10% Auto Savings:</span>
                    <strong style={{ color: '#16a34a' }}>+{formatKES(parseFloat(amount) * 0.1)}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <span style={{ color: 'var(--text-muted)' }}>Daily Contribution:</span>
                    <strong style={{ color: 'var(--brand-forest)' }}>+{formatKES(Math.max(50, parseFloat(amount) * 0.02))}</strong>
                  </div>
                </div>
              )}

              <div style={{ display: 'flex', gap: '10px', marginTop: '8px' }}>
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="btn btn-outline-forest"
                  style={{ flex: 1 }}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="btn btn-forest"
                  style={{ flex: 1 }}
                >
                  {submitting ? 'Recording...' : 'Record Income'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
