'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { MetricCard } from '@/components/ui/MetricCard';
import { Modal } from '@/components/ui/Modal';
import { formatKES, formatDate } from '@/lib/utils';
import { HeartPulse, ShieldCheck, PlusCircle, AlertCircle, CheckCircle2 } from 'lucide-react';

export default function WelfarePage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  const [claimModal, setClaimModal] = useState(false);
  const [claimType, setClaimType] = useState('Medical Emergency');
  const [amount, setAmount] = useState('');
  const [details, setDetails] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchWelfare = async () => {
    try {
      const res = await api.get('/member/welfare');
      if (res.status === 'success') {
        setData(res.data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchWelfare();
  }, []);

  const handleSubmitClaim = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) return;

    setSubmitting(true);
    try {
      await api.post('/member/submit_welfare_claim', {
        claim_type: claimType,
        amount: amt,
        details,
      });
      toast.success('Welfare claim submitted for review!');
      setClaimModal(false);
      setAmount('');
      setDetails('');
      fetchWelfare();
    } catch (err: any) {
      toast.error(err.message || 'Failed to submit claim.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '60px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Welfare Hub...</div>;
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Welfare & Solidarity Hub</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Emergency mutual aid, medical relief, and family solidarity support</p>
        </div>
        <button onClick={() => setClaimModal(true)} className="btn btn-lime">
          <PlusCircle size={16} /> Submit Welfare Claim
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard
          title="Cumulative Welfare Contributions"
          value={formatKES(data?.welfare_contributions || 0)}
          subtitle="Non-withdrawable mutual pool"
          variant="forest"
          icon={<HeartPulse size={24} />}
        />
        <MetricCard
          title="Member Protection Status"
          value="Active Coverage"
          subtitle="Qualified for all emergency aid claims"
          variant="lime"
          icon={<ShieldCheck size={24} />}
        />
      </div>

      {/* Claims Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>My Welfare Claims & Payouts</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Claim Category</th>
                <th>Claim Amount</th>
                <th>Details / Reason</th>
                <th>Status</th>
                <th>Submitted On</th>
              </tr>
            </thead>
            <tbody>
              {data?.claims?.length ? (
                data.claims.map((c: any, i: number) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{c.claim_type}</td>
                    <td style={{ fontWeight: 800, color: 'var(--text-main)' }}>{formatKES(c.amount)}</td>
                    <td style={{ color: 'var(--text-muted)', maxWidth: '300px' }}>{c.details || 'N/A'}</td>
                    <td>
                      <span className={`badge ${c.status === 'approved' ? 'badge-success' : c.status === 'rejected' ? 'badge-danger' : 'badge-warning'}`}>
                        {c.status}
                      </span>
                    </td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(c.created_at)}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    No welfare claims submitted.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Submit Claim Modal */}
      <Modal isOpen={claimModal} onClose={() => setClaimModal(false)} title="Submit Welfare Aid Claim">
        <form onSubmit={handleSubmitClaim} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label className="input-label">Claim Type</label>
            <select className="input-control" value={claimType} onChange={(e) => setClaimType(e.target.value)}>
              <option value="Medical Emergency">Medical Emergency / Hospitalization</option>
              <option value="Bereavement Support">Bereavement Support (Immediate Family)</option>
              <option value="Road Breakdown">Emergency Road Breakdown Relief</option>
              <option value="Disaster Relief">Disaster / Calamity Relief</option>
            </select>
          </div>
          <div>
            <label className="input-label">Claim Amount (KES)</label>
            <input
              type="number"
              min={500}
              step={100}
              className="input-control"
              placeholder="e.g. 20000"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="input-label">Detailed Incident Explanation</label>
            <textarea
              className="input-control"
              rows={4}
              placeholder="Please describe the incident, hospital details, or circumstances..."
              value={details}
              onChange={(e) => setDetails(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={submitting} className="btn btn-lime btn-lg" style={{ marginTop: '8px' }}>
            {submitting ? 'Submitting Claim...' : 'Submit Claim for Review'}
          </button>
        </form>
      </Modal>
    </div>
  );
}
