'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate } from '@/lib/utils';
import { Receipt, PlusCircle, ArrowRight } from 'lucide-react';

export default function AdminExpensesPage() {
  const { toast } = useToast();
  const [expenses, setExpenses] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Modal
  const [modalOpen, setModalOpen] = useState(false);
  const [category, setCategory] = useState('Operational');
  const [amount, setAmount] = useState('');
  const [payee, setPayee] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchExpenses = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/expenses');
      if (res.status === 'success') {
        setExpenses(res.data.expenses || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchExpenses();
  }, []);

  const handleAddExpense = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0 || !payee) return;

    setSubmitting(true);
    try {
      await api.post('/admin/add_expense', {
        category,
        amount: amt,
        payee,
        description,
      });
      toast.success('Expense recorded successfully.');
      setModalOpen(false);
      setAmount('');
      setPayee('');
      setDescription('');
      fetchExpenses();
    } catch (err: any) {
      toast.error(err.message || 'Failed to record expense.');
    } finally {
      setSubmitting(false);
    }
  };

  const totalExpenses = expenses.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Expense Management</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>
            Record, categorize, and track all operational and statutory expenditures
          </p>
        </div>
        <button onClick={() => setModalOpen(true)} className="btn btn-lime">
          <PlusCircle size={16} /> Record New Expense
        </button>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Expenditure Records</h3>
          <span style={{ fontWeight: 800, color: 'var(--brand-forest)' }}>
            Total Recorded: {formatKES(totalExpenses)}
          </span>
        </div>

        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Payee / Vendor</th>
                <th>Amount</th>
                <th>Description</th>
                <th>Recorded By</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {expenses.length ? (
                expenses.map((e, i) => (
                  <tr key={i}>
                    <td>
                      <span className="badge badge-info">{e.category}</span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{e.payee}</td>
                    <td style={{ fontWeight: 800, color: '#dc2626' }}>{formatKES(e.amount)}</td>
                    <td style={{ color: 'var(--text-muted)', maxWidth: '300px' }}>{e.description || '-'}</td>
                    <td style={{ fontSize: '0.85rem' }}>{e.recorded_by_name || 'Admin'}</td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(e.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading expenses...' : 'No expense records found.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add Expense Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="Record Operational Expense">
        <form onSubmit={handleAddExpense} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">Expense Category</label>
            <select className="input-control" value={category} onChange={(e) => setCategory(e.target.value)}>
              <option value="Operational">Operational / Office</option>
              <option value="Utilities">Utilities & Internet</option>
              <option value="Salaries">Staff Salaries & Allowances</option>
              <option value="Legal & Audit">Legal & Statutory Audit</option>
              <option value="IT & Infrastructure">IT Infrastructure & Software</option>
            </select>
          </div>

          <div>
            <label className="input-label">Payee / Vendor Name *</label>
            <input
              type="text"
              className="input-control"
              placeholder="e.g. Kenya Power / Landlord"
              value={payee}
              onChange={(e) => setPayee(e.target.value)}
              required
            />
          </div>

          <div>
            <label className="input-label">Amount (KES) *</label>
            <input
              type="number"
              min={1}
              step={10}
              className="input-control"
              placeholder="e.g. 15000"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
          </div>

          <div>
            <label className="input-label">Description / Memo</label>
            <textarea
              className="input-control"
              rows={3}
              placeholder="Purpose of expenditure..."
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>

          <button type="submit" disabled={submitting} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {submitting ? 'Recording...' : 'Record Expense Entry'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
