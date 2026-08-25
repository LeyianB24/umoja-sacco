'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate } from '@/lib/utils';
import { PiggyBank, Wallet, PlusCircle, ArrowDownRight, ArrowUpRight, Download } from 'lucide-react';

export default function SavingsPage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [depositModal, setDepositModal] = useState(false);
  const [amount, setAmount] = useState('');
  const [phone, setPhone] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchSavings = async () => {
    try {
      const res = await api.get('/member/savings');
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
    fetchSavings();
  }, []);

  const handleDeposit = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) return;

    setSubmitting(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: amt,
        phone,
        type: 'savings',
      });
      toast.success('M-Pesa STK Prompt dispatched to your phone.');
      setDepositModal(false);
      setAmount('');
      fetchSavings();
    } catch (err: any) {
      toast.error(err.message || 'Deposit failed.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '60px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Savings...</div>;
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Savings Portfolio</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Manage your compulsory and voluntary savings accounts</p>
        </div>
        <button onClick={() => setDepositModal(true)} className="btn btn-lime">
          <PlusCircle size={16} /> Deposit to Savings
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard
          title="Total Cumulative Savings"
          value={formatKES(data?.total_savings || 0)}
          subtitle="Earns compounding annual interest"
          variant="lime"
          icon={<PiggyBank size={24} />}
        />
        <MetricCard
          title="Withdrawable Wallet"
          value={formatKES(data?.wallet_balance || 0)}
          subtitle="Available for instant mobile payout"
          variant="forest"
          icon={<Wallet size={24} />}
        />
      </div>

      {/* Savings Ledger */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Savings Transactions Ledger</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Description</th>
                <th>Debit / Outflow</th>
                <th>Credit / Inflow</th>
                <th>Reference</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {data?.transactions?.length ? (
                data.transactions.map((tx: any, i: number) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 600 }}>{tx.description || 'Savings Entry'}</td>
                    <td style={{ color: tx.debit > 0 ? '#dc2626' : 'var(--text-muted)' }}>
                      {tx.debit > 0 ? formatKES(tx.debit) : '-'}
                    </td>
                    <td style={{ color: tx.credit > 0 ? '#16a34a' : 'var(--text-muted)', fontWeight: 700 }}>
                      {tx.credit > 0 ? `+${formatKES(tx.credit)}` : '-'}
                    </td>
                    <td><span style={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{tx.reference || 'N/A'}</span></td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(tx.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    No savings entries found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Deposit Modal */}
      <Modal isOpen={depositModal} onClose={() => setDepositModal(false)} title="Deposit to Savings">
        <form onSubmit={handleDeposit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">M-Pesa Phone Number</label>
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
            <label className="input-label">Deposit Amount (KES)</label>
            <input
              type="number"
              min={100}
              step={50}
              className="input-control"
              placeholder="e.g. 1000"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={submitting} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {submitting ? 'Sending Prompt...' : 'Send M-Pesa STK Prompt'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
