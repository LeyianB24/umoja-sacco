'use client';

import React, { useState } from 'react';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES } from '@/lib/utils';
import { PhoneCall, CheckCircle2, ArrowRight, ShieldCheck, Smartphone, Sparkles, RefreshCw, AlertCircle, Plus } from 'lucide-react';

export default function MpesaPaymentPage() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();

  const [phone, setPhone] = useState(user?.phone || '');
  const [amount, setAmount] = useState('1000');
  const [paymentType, setPaymentType] = useState('savings');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [ref, setRef] = useState('');
  const [checkoutId, setCheckoutId] = useState('');

  const presets = [500, 1000, 2500, 5000, 10000];

  const handlePay = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) {
      toast.error('Please enter a valid amount.');
      return;
    }

    setLoading(true);
    setSuccess(false);

    try {
      const res = await api.post('/member/mpesa_stk', {
        phone,
        amount: amt,
        type: paymentType,
      });

      setRef(res.data?.reference_no || res.data?.reference || `MP-${Date.now().toString().slice(-6)}`);
      setCheckoutId(res.data?.CheckoutRequestID || '');
      setSuccess(true);
      toast.success('M-Pesa STK Prompt dispatched to your phone! Please enter your PIN.');
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Payment initiation failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ maxWidth: '640px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <div className="eyebrow-pill" style={{ marginBottom: '6px' }}>
          <span className="eyebrow-dot" /> Safaricom Daraja STK Push
        </div>
        <h1 style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
          Instant M-Pesa Top-Up & Repayment
        </h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>
          Direct STK push to your Safaricom mobile phone with immediate ledger crediting
        </p>
      </div>

      <div className="card" style={{ padding: '36px', borderRadius: '24px' }}>
        {success ? (
          <div style={{ textAlign: 'center', padding: '16px 0' }}>
            <div
              style={{
                width: '68px',
                height: '68px',
                borderRadius: '50%',
                backgroundColor: 'rgba(22, 163, 74, 0.12)',
                color: '#16a34a',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 16px',
              }}
            >
              <Smartphone size={36} />
            </div>

            <h3 style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--brand-forest)', marginBottom: '8px' }}>
              STK Prompt Dispatched!
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '20px', lineHeight: 1.6 }}>
              A prompt has been sent to <strong style={{ color: 'var(--text-main)' }}>{phone}</strong>. Please check your phone screen and enter your M-Pesa PIN to complete payment of <strong style={{ color: 'var(--brand-forest)' }}>{formatKES(parseFloat(amount) || 0)}</strong>.
            </p>

            <div
              style={{
                padding: '14px 18px',
                borderRadius: '14px',
                backgroundColor: 'var(--surface-2)',
                border: '1px solid var(--border-color)',
                fontSize: '0.85rem',
                fontFamily: 'monospace',
                marginBottom: '28px',
                display: 'inline-block',
              }}
            >
              Transaction Ref: <strong>{ref}</strong>
            </div>

            <div style={{ display: 'flex', gap: '12px', justifyContent: 'center' }}>
              <button
                onClick={() => {
                  setSuccess(false);
                  setAmount('1000');
                }}
                className="btn btn-forest"
              >
                <Plus size={16} /> Make Another Payment
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={handlePay} style={{ display: 'flex', flexDirection: 'column', gap: '22px' }}>
            <div>
              <label className="input-label">Payment Category</label>
              <select
                className="form-select"
                value={paymentType}
                onChange={(e) => setPaymentType(e.target.value)}
              >
                <option value="savings">Deposit to Personal Savings Account</option>
                <option value="loan_repayment">Active Loan Repayment</option>
                <option value="shares">Purchase Sacco Capital Shares (KES 20/unit)</option>
                <option value="welfare">Welfare & Solidarity Contribution</option>
              </select>
            </div>

            <div>
              <label className="input-label">M-Pesa Phone Number</label>
              <input
                type="tel"
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
                min="10"
                step="10"
                className="input-control"
                placeholder="e.g. 1,000"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--brand-forest)' }}
                required
              />

              {/* Presets */}
              <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '10px' }}>
                {presets.map((p) => (
                  <button
                    key={p}
                    type="button"
                    onClick={() => setAmount(String(p))}
                    className={`btn ${amount === String(p) ? 'btn-forest' : 'btn-outline-forest'}`}
                    style={{ fontSize: '0.78rem', padding: '5px 12px', borderRadius: '50px' }}
                  >
                    KES {p.toLocaleString()}
                  </button>
                ))}
              </div>
            </div>

            <div style={{ padding: '14px', borderRadius: '14px', backgroundColor: 'var(--surface-2)', fontSize: '0.82rem', display: 'flex', alignItems: 'center', gap: '10px' }}>
              <ShieldCheck size={20} color="#16a34a" />
              <span>Direct integration with Safaricom Daraja API Paybill <strong>#247247</strong>.</span>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="btn btn-forest btn-lg"
              style={{ width: '100%', padding: '15px' }}
            >
              {loading ? 'Initiating Daraja STK Push...' : 'Send M-Pesa STK Prompt'}
              {!loading && <ArrowRight size={18} />}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
