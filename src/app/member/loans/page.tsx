'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate } from '@/lib/utils';
import { Banknote, ShieldAlert, PlusCircle, CreditCard, Clock, CheckCircle2, XCircle } from 'lucide-react';

export default function MemberLoansPage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Apply Modal
  const [applyModal, setApplyModal] = useState(false);
  const [loanType, setLoanType] = useState('personal');
  const [amount, setAmount] = useState('');
  const [duration, setDuration] = useState('12');
  const [purpose, setPurpose] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Repay Modal
  const [repayModal, setRepayModal] = useState(false);
  const [repayAmount, setRepayAmount] = useState('');
  const [repayPhone, setRepayPhone] = useState('');
  const [repaying, setRepaying] = useState(false);

  const fetchLoans = async () => {
    try {
      const res = await api.get('/member/loans');
      if (res.status === 'success') {
        setData(res.data);
      }
    } catch (err: any) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLoans();
  }, []);

  const handleApplyLoan = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) return;

    setSubmitting(true);
    try {
      await api.post('/member/apply_loan', {
        amount: amt,
        loan_type: loanType,
        duration_months: parseInt(duration),
        purpose,
      });
      toast.success('Loan application submitted for credit appraisal!');
      setApplyModal(false);
      setAmount('');
      setPurpose('');
      fetchLoans();
    } catch (err: any) {
      toast.error(err.message || 'Loan application failed.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleRepayLoan = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(repayAmount);
    if (isNaN(amt) || amt <= 0) return;

    setRepaying(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: amt,
        phone: repayPhone,
        type: 'loan_repayment',
      });
      toast.success('Repayment STK push sent to your phone.');
      setRepayModal(false);
      setRepayAmount('');
      fetchLoans();
    } catch (err: any) {
      toast.error(err.message || 'Repayment failed.');
    } finally {
      setRepaying(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '60px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Loans...</div>;
  }

  const maxLimit = data?.loan_limit || 50000;
  const activeBal = data?.active_balance || 0;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Loan Facilities</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Access affordable credit at 10% per annum with flexible terms</p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          {activeBal > 0 && (
            <button onClick={() => setRepayModal(true)} className="btn btn-outline-forest">
              <CreditCard size={16} /> Repay Loan
            </button>
          )}
          <button onClick={() => setApplyModal(true)} className="btn btn-lime">
            <PlusCircle size={16} /> Apply for Loan
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard
          title="Active Loan Exposure"
          value={formatKES(activeBal)}
          subtitle={activeBal > 0 ? 'Current outstanding balance' : 'No active debt'}
          variant={activeBal > 0 ? 'default' : 'forest'}
          icon={<Banknote size={24} color={activeBal > 0 ? '#dc2626' : undefined} />}
        />
        <MetricCard
          title="Eligible Loan Limit"
          value={formatKES(maxLimit)}
          subtitle="Based on 3x your active savings"
          variant="lime"
          icon={<ShieldAlert size={24} />}
        />
      </div>

      {/* Loans Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>My Loan Applications & Facilities</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Loan Reference</th>
                <th>Type</th>
                <th>Principal Amount</th>
                <th>Current Balance</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Applied On</th>
              </tr>
            </thead>
            <tbody>
              {data?.loans?.length ? (
                data.loans.map((loan: any, i: number) => {
                  let badgeClass = 'badge-warning';
                  if (loan.status === 'disbursed') badgeClass = 'badge-success';
                  if (loan.status === 'approved') badgeClass = 'badge-info';
                  if (loan.status === 'settled') badgeClass = 'badge-lime';
                  if (loan.status === 'rejected') badgeClass = 'badge-danger';

                  return (
                    <tr key={i}>
                      <td style={{ fontWeight: 700 }}>
                        <span style={{ fontFamily: 'monospace' }}>{loan.reference_no || `LN-${loan.loan_id}`}</span>
                      </td>
                      <td style={{ textTransform: 'capitalize' }}>{loan.loan_type}</td>
                      <td style={{ fontWeight: 700 }}>{formatKES(loan.amount)}</td>
                      <td style={{ fontWeight: 800, color: loan.current_balance > 0 ? '#dc2626' : 'var(--text-main)' }}>
                        {formatKES(loan.current_balance)}
                      </td>
                      <td>{loan.duration_months} Mos</td>
                      <td>
                        <span className={`badge ${badgeClass}`}>
                          {loan.status}
                        </span>
                      </td>
                      <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(loan.created_at)}</td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    No loan history found. Apply for a loan to get started.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Apply Loan Modal */}
      <Modal isOpen={applyModal} onClose={() => setApplyModal(false)} title="Apply for a Loan">
        <form onSubmit={handleApplyLoan} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">Loan Category</label>
            <select
              className="input-control"
              value={loanType}
              onChange={(e) => setLoanType(e.target.value)}
            >
              <option value="personal">Personal / Development Loan</option>
              <option value="emergency">Emergency / Instant Mobile Loan</option>
              <option value="school_fees">School Fees Loan</option>
              <option value="asset_finance">Vehicle / Asset Financing</option>
            </select>
          </div>

          <div>
            <label className="input-label">Requested Loan Amount (KES)</label>
            <input
              type="number"
              min={1000}
              max={maxLimit}
              step={1000}
              className="input-control"
              placeholder={`Max: ${maxLimit}`}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
            <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '4px' }}>
              Maximum limit: <b>{formatKES(maxLimit)}</b> (10% p.a. interest)
            </div>
          </div>

          <div>
            <label className="input-label">Repayment Duration</label>
            <select
              className="input-control"
              value={duration}
              onChange={(e) => setDuration(e.target.value)}
            >
              <option value="1">1 Month</option>
              <option value="3">3 Months</option>
              <option value="6">6 Months</option>
              <option value="12">12 Months (1 Year)</option>
              <option value="24">24 Months (2 Years)</option>
              <option value="36">36 Months (3 Years)</option>
            </select>
          </div>

          <div>
            <label className="input-label">Loan Purpose / Remarks</label>
            <textarea
              className="input-control"
              rows={3}
              placeholder="e.g. Vehicle maintenance, business expansion..."
              value={purpose}
              onChange={(e) => setPurpose(e.target.value)}
              required
            />
          </div>

          <button type="submit" disabled={submitting} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {submitting ? 'Submitting Application...' : 'Submit Loan Application'}
          </button>
        </form>
      </Modal>

      {/* Repay Loan Modal */}
      <Modal isOpen={repayModal} onClose={() => setRepayModal(false)} title="Repay Loan via M-Pesa">
        <form onSubmit={handleRepayLoan} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">M-Pesa Phone Number</label>
            <input
              type="text"
              className="input-control"
              placeholder="e.g. 0712345678"
              value={repayPhone}
              onChange={(e) => setRepayPhone(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="input-label">Repayment Amount (KES)</label>
            <input
              type="number"
              min={100}
              step={50}
              className="input-control"
              placeholder={`Outstanding: ${activeBal}`}
              value={repayAmount}
              onChange={(e) => setRepayAmount(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={repaying} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {repaying ? 'Sending STK Push...' : 'Send Repayment Prompt'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
