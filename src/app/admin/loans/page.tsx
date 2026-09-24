'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import { Banknote, Filter, CheckCircle2, Clock, Printer } from 'lucide-react';

export default function AdminLoansMasterPage() {
  const [loans, setLoans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState('');

  const fetchLoans = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/loans', { status });
      if (res.status === 'success') {
        setLoans(res.data.loans || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLoans();
  }, [status]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Loan Portfolio Directory</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Master list of all member credit applications, active debt, and settled facilities</p>
        </div>

        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
          <button
            onClick={() => window.print()}
            className="btn btn-outline-forest"
            style={{ borderRadius: '50px', padding: '10px 18px' }}
          >
            <Printer size={16} /> Print Loans Report
          </button>
          <Link href="/admin/loans/reviews" className="btn btn-lime">
            <Clock size={16} /> Loan Review Queue
          </Link>
          <Link href="/admin/loans/payouts" className="btn btn-forest">
            <Banknote size={16} /> Disbursement Queue
          </Link>
        </div>
      </div>

      {/* Filter */}
      <div className="card" style={{ padding: '16px 20px', display: 'flex', gap: '16px', alignItems: 'center' }}>
        <Filter size={16} style={{ color: 'var(--text-muted)' }} />
        <select className="input-control" style={{ width: 'auto' }} value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All Loan Statuses</option>
          <option value="pending">Pending Review</option>
          <option value="approved">Approved (Pending Payout)</option>
          <option value="disbursed">Disbursed (Active Debt)</option>
          <option value="settled">Settled / Repaid</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {/* Loans Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Loan Reference</th>
                <th>Member Name (Reg No)</th>
                <th>Type</th>
                <th>Principal</th>
                <th>Balance</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Applied On</th>
              </tr>
            </thead>
            <tbody>
              {loans.length ? (
                loans.map((l, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>{l.reference_no || `LN-${l.loan_id}`}</td>
                    <td>
                      <div style={{ fontWeight: 600 }}>{l.member_name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{l.member_reg_no}</div>
                    </td>
                    <td style={{ textTransform: 'capitalize' }}>{l.loan_type}</td>
                    <td style={{ fontWeight: 700 }}>{formatKES(l.amount)}</td>
                    <td style={{ fontWeight: 800, color: l.current_balance > 0 ? '#dc2626' : 'var(--text-main)' }}>{formatKES(l.current_balance)}</td>
                    <td>{l.duration_months} Mos</td>
                    <td>
                      <span className={`badge ${l.status === 'disbursed' ? 'badge-success' : l.status === 'approved' ? 'badge-info' : l.status === 'rejected' ? 'badge-danger' : 'badge-warning'}`}>
                        {l.status}
                      </span>
                    </td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(l.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={8} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading loan portfolio...' : 'No loan records found.'}
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
