'use client';

import React from 'react';
import { BarChart3, Download, FileSpreadsheet, TrendingUp, PieChart, Users } from 'lucide-react';

export default function AdminReportsPage() {
  const reports = [
    { title: 'Statement of Financial Position (Balance Sheet)', desc: 'Consolidated Assets, Liabilities, and Member Equity Capital' },
    { title: 'Statement of Comprehensive Income (P&L)', desc: 'Operational revenue, interest inflows, staff payroll, and expenses' },
    { title: 'Loan Portfolio Performance & Aging Analysis', desc: 'Active debt, performing loans, PAR analysis, and risk provisions' },
    { title: 'Member Growth & Savings Inflow Analytics', desc: 'New admissions, member retention, and monthly contribution metrics' },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Analytical Financial Reports</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Generate regulatory SASRA and management financial reporting packages</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '24px' }}>
        {reports.map((r, i) => (
          <div key={i} className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
            <div>
              <div style={{ width: '44px', height: '44px', borderRadius: '12px', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '16px' }}>
                <BarChart3 size={22} />
              </div>
              <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '8px' }}>{r.title}</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.5, marginBottom: '24px' }}>{r.desc}</p>
            </div>
            <button
              onClick={() => window.print()}
              className="btn btn-outline-forest"
              style={{ width: '100%' }}
            >
              <Download size={16} /> Generate & Export Report
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
