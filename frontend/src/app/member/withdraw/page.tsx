'use client';

import React, { useState } from 'react';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES } from '@/lib/utils';
import { Wallet, CheckCircle2, ArrowRight, ShieldCheck } from 'lucide-react';

export default function WithdrawPage() {
  const { user, balances, refreshUser } = useAuth();
  const { toast } = useToast();

  const [phone, setPhone] = useState(user?.phone || '');
  const [amount, setAmount] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [ref, setRef] = useState('');

  const available = (balances?.wallet || 0) + (balances?.savings || 0);

  const handleWithdraw = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) return;

    if (amt > available) {
      toast.error('Withdrawal amount exceeds your available balance.');
      return;
    }

    setLoading(true);
    setSuccess(false);

    try {
      const res = await api.post('/member/withdraw', {
        phone,
        amount: amt,
      });

      setRef(res.data?.reference || 'WTH-' + Date.now());
      setSuccess(true);
      toast.success('Withdrawal request submitted successfully.');
      await refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Withdrawal request failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ maxWidth: '600px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Withdraw Funds</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Transfer funds from your Sacco wallet/savings to your registered M-Pesa phone</p>
      </div>

      <div className="card" style={{ padding: '36px' }}>
        <div style={{ backgroundColor: 'var(--surface-2)', padding: '16px 20px', borderRadius: '16px', marginBottom: '24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Available Withdrawable Funds</div>
            <div style={{ fontSize: '1.4rem', fontWeight: 800, color: 'var(--brand-forest)', marginTop: '2px' }}>
              {formatKES(available)}
            </div>
          </div>
          <span className="badge badge-success">Instant Payout</span>
        </div>

        {success ? (
          <div style={{ textAlign: 'center', padding: '20px 0' }}>
            <CheckCircle2 size={54} color="#16a34a" style={{ margin: '0 auto 16px' }} />
            <h3 style={{ fontSize: '1.25rem', fontWeight: 800, marginBottom: '8px' }}>Withdrawal Request Logged</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '20px', lineHeight: 1.5 }}>
              Your payout of <b>{formatKES(amount)}</b> is being processed to <b>{phone}</b>.
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
              Make Another Withdrawal
            </button>
          </div>
        ) : (
          <form onSubmit={handleWithdraw} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            <div>
              <label className="input-label">M-Pesa Destination Phone Number</label>
              <input
                type="text"
                className="input-control"
                placeholder="e.g. 0712345678"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
              />
            </div>

            <div>
              <label className="input-label">Withdrawal Amount (KES)</label>
              <input
                type="number"
                min={100}
                max={available}
                step={50}
                className="input-control"
                placeholder={`Max available: ${available}`}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                required
              />
            </div>

            <button type="submit" disabled={loading || available <= 0} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
              {loading ? 'Processing...' : 'Request M-Pesa Payout'} <ArrowRight size={18} />
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
