'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { formatKES, formatDate } from '@/lib/utils';
import {
  ArrowLeft,
  ShieldCheck,
  CheckCircle2,
  XCircle,
  FileText,
  Wallet,
  PiggyBank,
  PieChart,
  Banknote,
} from 'lucide-react';

export default function AdminMemberDetailPage() {
  const { id } = useParams();
  const router = useRouter();
  const { toast } = useToast();

  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [verifying, setVerifying] = useState(false);

  const fetchDetail = async () => {
    try {
      const res = await api.get('/admin/member_detail', { id });
      if (res.status === 'success') {
        setData(res.data);
      }
    } catch (err: any) {
      toast.error(err.message || 'Failed to load member profile.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (id) fetchDetail();
  }, [id]);

  const handleKycAction = async (status: 'approved' | 'rejected') => {
    setVerifying(true);
    try {
      await api.post('/admin/verify_kyc', {
        member_id: id,
        status,
        notes: `KYC ${status} by administrator.`,
      });
      toast.success(`Member KYC status updated to ${status}.`);
      fetchDetail();
    } catch (err: any) {
      toast.error(err.message || 'KYC verification update failed.');
    } finally {
      setVerifying(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '80px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Member File...</div>;
  }

  const m = data?.member || {};
  const b = data?.balances || { wallet: 0, savings: 0, shares: 0, loans: 0 };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <button onClick={() => router.back()} className="btn btn-sm btn-ghost" style={{ marginBottom: '8px' }}>
            <ArrowLeft size={16} /> Back to Members List
          </button>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>
            {m.full_name} ({m.member_reg_no})
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>
            ID: {m.national_id} • Phone: {m.phone} • Email: {m.email}
          </p>
        </div>

        {/* KYC Verification actions */}
        <div style={{ display: 'flex', gap: '10px' }}>
          {m.kyc_status !== 'approved' && (
            <button
              disabled={verifying}
              onClick={() => handleKycAction('approved')}
              className="btn btn-lime"
            >
              <CheckCircle2 size={16} /> Approve KYC
            </button>
          )}
          {m.kyc_status !== 'rejected' && (
            <button
              disabled={verifying}
              onClick={() => handleKycAction('rejected')}
              className="btn btn-outline-forest"
              style={{ color: '#dc2626', borderColor: '#dc2626' }}
            >
              <XCircle size={16} /> Reject KYC
            </button>
          )}
        </div>
      </div>

      {/* Financial Balances Summary */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
        <MetricCard title="Savings Balance" value={formatKES(b.savings)} variant="lime" icon={<PiggyBank size={22} />} />
        <MetricCard title="Shares Capital" value={formatKES(b.shares)} variant="forest" icon={<PieChart size={22} />} />
        <MetricCard title="Wallet Balance" value={formatKES(b.wallet)} variant="default" icon={<Wallet size={22} />} />
        <MetricCard title="Active Debt" value={formatKES(b.loans)} variant="default" icon={<Banknote size={22} color={b.loans > 0 ? '#dc2626' : undefined} />} />
      </div>

      {/* Member Details & Documents */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '24px' }}>
        {/* Personal & Next of Kin */}
        <div className="card">
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '16px', borderBottom: '1px solid var(--border-color)', paddingBottom: '8px' }}>
            Member Bio & Profile
          </h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', fontSize: '0.9rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-muted)' }}>Status:</span>
              <span className={`badge ${m.status === 'active' ? 'badge-success' : 'badge-danger'}`}>{m.status}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-muted)' }}>KYC Status:</span>
              <span className={`badge ${m.kyc_status === 'approved' ? 'badge-success' : 'badge-warning'}`}>{m.kyc_status || 'Pending'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-muted)' }}>Occupation:</span>
              <span style={{ fontWeight: 600 }}>{m.occupation || 'N/A'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-muted)' }}>Address:</span>
              <span style={{ fontWeight: 600 }}>{m.address || 'N/A'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-muted)' }}>Next of Kin:</span>
              <span style={{ fontWeight: 700 }}>{m.next_of_kin_name || 'N/A'} ({m.next_of_kin_phone || 'N/A'})</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-muted)' }}>Joined Sacco:</span>
              <span>{formatDate(m.created_at || m.join_date)}</span>
            </div>
          </div>
        </div>

        {/* KYC Uploaded Documents */}
        <div className="card">
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '16px', borderBottom: '1px solid var(--border-color)', paddingBottom: '8px' }}>
            KYC Identification Documents
          </h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            {data?.documents?.length ? (
              data.documents.map((d: any, i: number) => (
                <div
                  key={i}
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '12px 14px',
                    borderRadius: '12px',
                    backgroundColor: 'var(--surface-2)',
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <FileText size={18} color="var(--brand-forest)" />
                    <div>
                      <div style={{ fontWeight: 600, fontSize: '0.88rem', textTransform: 'capitalize' }}>
                        {d.document_type?.replace(/_/g, ' ')}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
                        {d.original_filename || 'Identity Doc'}
                      </div>
                    </div>
                  </div>
                  <span className={`badge ${d.status === 'approved' ? 'badge-success' : 'badge-warning'}`}>
                    {d.status || 'Pending'}
                  </span>
                </div>
              ))
            ) : (
              <div style={{ textAlign: 'center', padding: '24px 0', color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                No digital documents uploaded.
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Loan History */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Loan Facilities Record</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Principal Amount</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Date Applied</th>
              </tr>
            </thead>
            <tbody>
              {data?.loans?.length ? (
                data.loans.map((l: any, i: number) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>{l.reference_no || `LN-${l.loan_id}`}</td>
                    <td style={{ textTransform: 'capitalize' }}>{l.loan_type}</td>
                    <td style={{ fontWeight: 700 }}>{formatKES(l.amount)}</td>
                    <td style={{ fontWeight: 800, color: l.current_balance > 0 ? '#dc2626' : 'var(--text-main)' }}>{formatKES(l.current_balance)}</td>
                    <td><span className={`badge ${l.status === 'disbursed' ? 'badge-success' : 'badge-warning'}`}>{l.status}</span></td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(l.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '28px', color: 'var(--text-muted)' }}>
                    No loans on file for this member.
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
