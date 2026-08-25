'use client';

import React, { useState } from 'react';
import { FileText, Download, Printer, Search } from 'lucide-react';
import { useToast } from '@/context/ToastContext';

export default function AdminStatementsPage() {
  const { toast } = useToast();
  const [memberId, setMemberId] = useState('');
  const [startDate, setStartDate] = useState(new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]);
  const [endDate, setEndDate] = useState(new Date().toISOString().split('T')[0]);

  const handleGenerate = (e: React.FormEvent) => {
    e.preventDefault();
    toast.success('Certified statement generated!');
    window.print();
  };

  return (
    <div style={{ maxWidth: '700px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Certified Account Statements</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Generate official, certified financial statements for individual members or general audits</p>
      </div>

      <div className="card" style={{ padding: '36px' }}>
        <form onSubmit={handleGenerate} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
          <div>
            <label className="input-label">Member Identification / Reg No</label>
            <input
              type="text"
              className="input-control"
              placeholder="e.g. USMS-2026-0001 or Member ID"
              value={memberId}
              onChange={(e) => setMemberId(e.target.value)}
              required
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
            <div>
              <label className="input-label">Statement Start Date</label>
              <input
                type="date"
                className="input-control"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="input-label">Statement End Date</label>
              <input
                type="date"
                className="input-control"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                required
              />
            </div>
          </div>

          <button type="submit" className="btn btn-lime btn-lg" style={{ marginTop: '10px' }}>
            <FileText size={18} /> Generate Certified Statement
          </button>
        </form>
      </div>
    </div>
  );
}
