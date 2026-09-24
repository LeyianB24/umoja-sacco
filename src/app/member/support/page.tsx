'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { Modal } from '@/components/ui/Modal';
import { formatDate } from '@/lib/utils';
import { Headphones, PlusCircle, MessageSquare, CheckCircle2, Clock } from 'lucide-react';

export default function MemberSupportPage() {
  const { toast } = useToast();
  const [tickets, setTickets] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Modal
  const [ticketModal, setTicketModal] = useState(false);
  const [subject, setSubject] = useState('');
  const [category, setCategory] = useState('Loan Inquiry');
  const [priority, setPriority] = useState('Normal');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchTickets = async () => {
    try {
      const res = await api.get('/member/support_tickets');
      if (res.status === 'success') {
        setTickets(res.data.tickets || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, []);

  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!subject || !message) return;

    setSubmitting(true);
    try {
      await api.post('/member/create_ticket', {
        subject,
        category,
        priority,
        message,
      });
      toast.success('Support ticket created. An officer will reply shortly.');
      setTicketModal(false);
      setSubject('');
      setMessage('');
      fetchTickets();
    } catch (err: any) {
      toast.error(err.message || 'Failed to create ticket.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Member Support Desk</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Open a support ticket for assistance with transactions, loans, or KYC</p>
        </div>
        <button onClick={() => setTicketModal(true)} className="btn btn-lime">
          <PlusCircle size={16} /> Open New Ticket
        </button>
      </div>

      {/* Tickets List */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Ticket ID</th>
                <th>Subject</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date Opened</th>
              </tr>
            </thead>
            <tbody>
              {tickets.length ? (
                tickets.map((t, i) => (
                  <tr key={i}>
                    <td>
                      <span style={{ fontFamily: 'monospace', fontWeight: 700 }}>
                        #TK-{t.id || t.ticket_id}
                      </span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{t.subject}</td>
                    <td>{t.category || 'General'}</td>
                    <td>
                      <span className={`badge ${t.priority === 'High' ? 'badge-danger' : 'badge-info'}`}>
                        {t.priority || 'Normal'}
                      </span>
                    </td>
                    <td>
                      <span className={`badge ${t.status === 'Closed' ? 'badge-success' : 'badge-warning'}`}>
                        {t.status || 'Open'}
                      </span>
                    </td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(t.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading tickets...' : 'No support tickets found.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Create Ticket Modal */}
      <Modal isOpen={ticketModal} onClose={() => setTicketModal(false)} title="Open Support Ticket">
        <form onSubmit={handleCreateTicket} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">Subject</label>
            <input
              type="text"
              className="input-control"
              placeholder="e.g. Loan Repayment Reconciliation Inquiry"
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              required
            />
          </div>

          <div>
            <label className="input-label">Category</label>
            <select className="input-control" value={category} onChange={(e) => setCategory(e.target.value)}>
              <option value="Loan Inquiry">Loan Inquiry & Appraisal</option>
              <option value="Savings / Deposit">Savings / M-Pesa Deposit Issue</option>
              <option value="Welfare Claim">Welfare Aid Claim Query</option>
              <option value="KYC / Profile">KYC & Document Verification</option>
              <option value="Technical Issue">Portal Technical Bug</option>
            </select>
          </div>

          <div>
            <label className="input-label">Priority</label>
            <select className="input-control" value={priority} onChange={(e) => setPriority(e.target.value)}>
              <option value="Normal">Normal</option>
              <option value="High">High</option>
              <option value="Critical">Critical Emergency</option>
            </select>
          </div>

          <div>
            <label className="input-label">Message Details</label>
            <textarea
              className="input-control"
              rows={4}
              placeholder="Please provide full details so we can assist promptly..."
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              required
            />
          </div>

          <button type="submit" disabled={submitting} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {submitting ? 'Creating Ticket...' : 'Submit Support Ticket'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
