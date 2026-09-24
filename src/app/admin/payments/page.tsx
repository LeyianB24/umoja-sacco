'use client';

import React, { useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES } from '@/lib/utils';
import { CreditCard, CheckCircle2, ArrowRight, Printer, Receipt } from 'lucide-react';

export default function AdminPaymentsPage() {
  const { toast } = useToast();

  const [memberId, setMemberId] = useState('');
  const [amount, setAmount] = useState('');
  const [paymentType, setPaymentType] = useState('savings');
  const [method, setMethod] = useState('cash');
  const [notes, setNotes] = useState('Over the counter teller deposit');
  const [loading, setLoading] = useState(false);
  const [receipt, setReceipt] = useState<any>(null);

  const handleProcess = async (e: React.FormEvent) => {
    e.preventDefault();
    const mId = parseInt(memberId);
    const amt = parseFloat(amount);
    if (!mId || !amt) return;

    setLoading(true);
    try {
      const res = await api.post('/admin/process_payment', {
        member_id: mId,
        amount: amt,
        payment_type: paymentType,
        method,
        notes,
      });

      setReceipt(res.data);
      toast.success('Payment received and posted to ledger!');
    } catch (err: any) {
      toast.error(err.message || 'Payment processing failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ maxWidth: '720px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Cashier & Teller Desk</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Receive over-the-counter payments and post instantly to the member's account ledger</p>
      </div>

      <div className="card" style={{ padding: '36px' }}>
        {receipt ? (
          <div style={{ textAlign: 'center', padding: '20px 0' }}>
            <CheckCircle2 size={54} color="#16a34a" style={{ margin: '0 auto 16px' }} />
            <h3 style={{ fontSize: '1.25rem', fontWeight: 800, marginBottom: '4px' }}>Payment Successfully Posted</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', marginBottom: '24px' }}>
              Official Sacco receipt generated
            </p>

            {/* Receipt Box */}
            <div
              style={{
                backgroundColor: 'var(--surface-2)',
                border: '1px dashed var(--border-color)',
                borderRadius: '16px',
                padding: '24px',
                textAlign: 'left',
                marginBottom: '28px',
                display: 'flex',
                flexDirection: 'column',
                gap: '10px',
                fontSize: '0.9rem',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-muted)' }}>Receipt Number:</span>
                <span style={{ fontWeight: 800, fontFamily: 'monospace' }}>{receipt.receipt_no}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-muted)' }}>Amount Paid:</span>
                <span style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.1rem' }}>{formatKES(receipt.amount)}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-muted)' }}>Payment For:</span>
                <span style={{ fontWeight: 700, textTransform: 'capitalize' }}>{receipt.type?.replace('_', ' ')}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-muted)' }}>Date:</span>
                <span>{receipt.date}</span>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'center', gap: '14px' }}>
              <button
                onClick={() => window.print()}
                className="btn btn-outline-forest"
              >
                <Printer size={16} /> Print Receipt
              </button>
              <button
                onClick={() => {
                  setReceipt(null);
                  setAmount('');
                  setMemberId('');
                }}
                className="btn btn-lime"
              >
                Process Next Payment
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleProcess} style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
              <div>
                <label className="input-label">Member Database ID *</label>
                <input
                  type="number"
                  min={1}
                  className="input-control"
                  placeholder="e.g. 1"
                  value={memberId}
                  onChange={(e) => setMemberId(e.target.value)}
                  required
                />
              </div>

              <div>
                <label className="input-label">Amount Received (KES) *</label>
                <input
                  type="number"
                  min={1}
                  step={10}
                  className="input-control"
                  placeholder="e.g. 5000"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  required
                />
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
              <div>
                <label className="input-label">Payment Category</label>
                <select className="input-control" value={paymentType} onChange={(e) => setPaymentType(e.target.value)}>
                  <option value="savings">Deposit to Savings</option>
                  <option value="loan_repayment">Loan Repayment</option>
                  <option value="shares">Share Capital Equity Purchase</option>
                  <option value="welfare">Welfare Pool Contribution</option>
                  <option value="registration">Registration Fee</option>
                </select>
              </div>

              <div>
                <label className="input-label">Payment Tender Method</label>
                <select className="input-control" value={method} onChange={(e) => setMethod(e.target.value)}>
                  <option value="cash">Cash Tender</option>
                  <option value="mpesa">Direct M-Pesa Paybill</option>
                  <option value="bank">Bank Slip / Cheque Deposit</option>
                </select>
              </div>
            </div>

            <div>
              <label className="input-label">Teller Remarks / Reference</label>
              <input
                type="text"
                className="input-control"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </div>

            <button type="submit" disabled={loading} className="btn btn-lime btn-lg" style={{ marginTop: '10px' }}>
              {loading ? 'Processing Payment...' : 'Confirm Receipt & Post to Ledger'} <ArrowRight size={18} />
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
