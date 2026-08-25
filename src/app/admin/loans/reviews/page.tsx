'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate } from '@/lib/utils';
import { Clock, CheckCircle2, XCircle, FileText } from 'lucide-react';

export default function AdminLoanReviewsPage() {
  const { toast } = useToast();
  const [loans, setLoans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Review modal
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedLoan, setSelectedLoan] = useState<any>(null);
  const [actionStatus, setActionStatus] = useState<'approved' | 'rejected'>('approved');
  const [reviewNotes, setReviewNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchPendingLoans = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/loans', { status: 'pending' });
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
    fetchPendingLoans();
  }, []);

  const openReviewModal = (loan: any, status: 'approved' | 'rejected') => {
    setSelectedLoan(loan);
    setActionStatus(status);
    setReviewNotes('');
    setModalOpen(true);
  };

  const handleReviewSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedLoan) return;

    setSubmitting(true);
    try {
      await api.post('/admin/review_loan', {
        loan_id: selectedLoan.loan_id,
        status: actionStatus,
        notes: reviewNotes,
      });
      toast.success(`Loan application ${actionStatus} successfully.`);
      setModalOpen(false);
      fetchPendingLoans();
    } catch (err: any) {
      toast.error(err.message || 'Loan review submission failed.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Loan Appraisal & Review Queue</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Review pending member loan applications and approve or reject with comments</p>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Member (Reg No)</th>
                <th>Phone</th>
                <th>Requested Amount</th>
                <th>Duration</th>
                <th>Purpose</th>
                <th>Date Applied</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loans.length ? (
                loans.map((loan, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>{loan.reference_no || `LN-${loan.loan_id}`}</td>
                    <td>
                      <div style={{ fontWeight: 600 }}>{loan.member_name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{loan.member_reg_no}</div>
                    </td>
                    <td>{loan.phone}</td>
                    <td style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>{formatKES(loan.amount)}</td>
                    <td>{loan.duration_months} Months</td>
                    <td style={{ color: 'var(--text-muted)', maxWidth: '250px' }}>{loan.notes || 'Personal loan'}</td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(loan.created_at)}</td>
                    <td>
                      <div style={{ display: 'flex', gap: '6px' }}>
                        <button
                          onClick={() => openReviewModal(loan, 'approved')}
                          className="btn btn-sm btn-lime"
                          style={{ padding: '4px 10px', fontSize: '0.78rem' }}
                        >
                          <CheckCircle2 size={14} /> Approve
                        </button>
                        <button
                          onClick={() => openReviewModal(loan, 'rejected')}
                          className="btn btn-sm btn-outline-forest"
                          style={{ color: '#dc2626', borderColor: '#dc2626', padding: '4px 10px', fontSize: '0.78rem' }}
                        >
                          <XCircle size={14} /> Reject
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={8} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Checking review queue...' : 'No pending loan applications awaiting review.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Review Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={`${actionStatus === 'approved' ? 'Approve' : 'Reject'} Loan Application`}
      >
        <form onSubmit={handleReviewSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div style={{ backgroundColor: 'var(--surface-2)', padding: '14px', borderRadius: '12px' }}>
            <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Applicant</div>
            <div style={{ fontWeight: 700 }}>{selectedLoan?.member_name} ({selectedLoan?.member_reg_no})</div>
            <div style={{ fontSize: '0.88rem', fontWeight: 800, color: 'var(--brand-forest)', marginTop: '4px' }}>
              Amount: {formatKES(selectedLoan?.amount)} • Duration: {selectedLoan?.duration_months} Months
            </div>
          </div>

          <div>
            <label className="input-label">Credit Officer Appraisal Notes / Remarks</label>
            <textarea
              className="input-control"
              rows={3}
              placeholder="e.g. Credit score verified, guarantor checked. Approved for payout."
              value={reviewNotes}
              onChange={(e) => setReviewNotes(e.target.value)}
              required
            />
          </div>

          <button
            type="submit"
            disabled={submitting}
            className={`btn ${actionStatus === 'approved' ? 'btn-lime' : 'btn-outline-forest'}`}
            style={{
              marginTop: '6px',
              backgroundColor: actionStatus === 'rejected' ? '#dc2626' : undefined,
              color: actionStatus === 'rejected' ? '#FFFFFF' : undefined,
            }}
          >
            {submitting ? 'Submitting...' : `Confirm ${actionStatus === 'approved' ? 'Approval' : 'Rejection'}`}
          </button>
        </form>
      </Modal>
    </div>
  );
}
