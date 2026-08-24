'use client';

import React, { useState } from 'react';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES } from '@/lib/utils';
import { PhoneCall, CheckCircle2, ArrowRight, ShieldCheck } from 'lucide-react';

export default function MpesaPaymentPage() {
  const { user } = useAuth();
  const { toast } = useToast();

  const [phone, setPhone] = useState(user?.phone || '');
  const [amount, setAmount] = useState('');
  const [paymentType, setPaymentType] = useState('savings');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [ref, setRef] = useState('');

  const handlePay = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) return;

    setLoading(true);
    setSuccess(false);

    try {
      const res = await api.post('/member/mpesa_stk', {
        phone,
        amount: amt,
        type: paymentType,
      });

      setRef(res.data?.reference || 'STK-' + Date.now());
      setSuccess(true);
      toast.success('M-Pesa STK Prompt sent! Please enter your PIN on your phone.');
    } catch (err: any) {
      toast.error(err.message || 'Payment initiation failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ maxWidth: '600px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Pay via M-Pesa</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Instant account deposit, loan repayment, or share capital purchase</p>
      </div>

      <div className="card" style={{ padding: '36px' }}>
        {success ? (
          <div style={{ textAlign: 'center', padding: '20px 0' }}>
            <CheckCircle2 size={54} color="#16a34a" style={{ margin: '0 auto 16px' }} />
            <h3 style={{ fontSize: '1.25rem', fontWeight: 800, marginBottom: '8px' }}>M-Pesa Prompt Dispatched!</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '20px', lineHeight: 1.5 }}>
              Check your mobile phone for the Safaricom STK prompt and enter your M-Pesa PIN to complete payment of <b>{formatKES(amount)}</b>.
            </p>
            <div style={{ padding: '12px', borderRadius: '12px', backgroundColor: 'var(--surface-2)', fontSize: '0.82rem', fontFamily: 'monospace', marginBottom: '24px' }}>
              Reference ID: {ref}
            </div>
            <button
              onClick={() => {
                setSuccess(false);
                setAmount('');
              }}
              className="btn btn-lime"
            >
              Make Another Payment
            </button>
          </div>
        ) : (
          <form onSubmit={handlePay} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            <div>
              <label className="input-label">Payment Category</label>
              <select
                className="input-control"
                value={paymentType}
                onChange={(e) => setPaymentType(e.target.value)}
              >
                <option value="savings">Deposit to Savings Account</option>
                <option value="loan_repayment">Loan Repayment</option>
                <option value="shares">Purchase Co-op Shares</option>
                <option value="welfare">Welfare Fund Contribution</option>
                <option value="registration">Registration Fee</option>
              </select>
            </div>

            <div>
              <label className="input-label">M-Pesa Phone Number</label>
              <input
                type="text"
                className="input-control"
                placeholder="e.g. 0712345678 or +2547..."
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
              />
            </div>

            <div>
              <label className="input-label">Amount (KES)</label>
              <input
                type="number"
                min={10}
                step={10}
                className="input-control"
                placeholder="e.g. 2000"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                required
              />
            </div>

            <button type="submit" disabled={loading} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
              {loading ? 'Sending Prompt...' : 'Send M-Pesa Prompt'} <ArrowRight size={18} />
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
