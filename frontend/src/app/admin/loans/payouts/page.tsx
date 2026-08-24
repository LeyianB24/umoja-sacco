'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate } from '@/lib/utils';
import { Banknote, Coins, ArrowRight, CheckCircle2 } from 'lucide-react';

export default function AdminLoanPayoutsPage() {
  const { toast } = useToast();
  const [loans, setLoans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Payout modal
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedLoan, setSelectedLoan] = useState<any>(null);
  const [method, setMethod] = useState('mpesa');
  const [paying, setPaying] = useState(false);

  const fetchApprovedLoans = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/loans', { status: 'approved' });
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
    fetchApprovedLoans();
  }, []);

  const openPayoutModal = (loan: any) => {
    setSelectedLoan(loan);
    setMethod('mpesa');
    setModalOpen(true);
  };

  const handlePayoutSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedLoan) return;

    setPaying(true);
    try {
      const res = await api.post('/admin/payout_loan', {
        loan_id: selectedLoan.loan_id,
        disbursement_method: method,
      });
      toast.success(res.message || 'Loan disbursed successfully.');
      setModalOpen(false);
      fetchApprovedLoans();
    } catch (err: any) {
      toast.error(err.message || 'Loan payout failed.');
    } finally {
      setPaying(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Loan Disbursement & Payout Queue</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Approved member loans ready for instant mobile M-Pesa or bank transfer disbursement</p>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Member (Reg No)</th>
                <th>Phone Number</th>
                <th>Approved Amount</th>
                <th>Approved By</th>
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
                    <td style={{ fontWeight: 800, color: '#16a34a', fontSize: '1rem' }}>
                      {formatKES(loan.amount)}
                    </td>
                    <td style={{ fontSize: '0.85rem' }}>{loan.approved_by_name || 'Credit Manager'}</td>
                    <td>
                      <button
                        onClick={() => openPayoutModal(loan)}
                        className="btn btn-sm btn-lime"
                        style={{ padding: '6px 14px' }}
                      >
                        <Coins size={14} /> Disburse Payout
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Checking disbursement queue...' : 'No approved loans waiting in disbursement queue.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Payout Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="Process Loan Disbursement">
        <form onSubmit={handlePayoutSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div style={{ backgroundColor: 'var(--surface-2)', padding: '16px', borderRadius: '12px' }}>
            <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Recipient Member</div>
            <div style={{ fontWeight: 700, fontSize: '1rem' }}>{selectedLoan?.member_name} ({selectedLoan?.member_reg_no})</div>
            <div style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--brand-forest)', marginTop: '6px' }}>
              Payout Amount: {formatKES(selectedLoan?.amount)}
            </div>
          </div>

          <div>
            <label className="input-label">Disbursement Channel</label>
            <select className="input-control" value={method} onChange={(e) => setMethod(e.target.value)}>
              <option value="mpesa">Safaricom M-Pesa B2C (Instant Mobile Payout)</option>
              <option value="bank">Direct Bank Transfer (EFT / RTGS)</option>
              <option value="cheque">Sacco Cheque Issuance</option>
            </select>
          </div>

          <button type="submit" disabled={paying} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {paying ? 'Executing Disbursal...' : 'Confirm & Execute Payout'} <ArrowRight size={16} />
          </button>
        </form>
      </Modal>
    </div>
  );
}
