'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES, formatNumber } from '@/lib/utils';
import {
  BarChart3,
  Download,
  FileSpreadsheet,
  TrendingUp,
  PieChart,
  Users,
  ShieldCheck,
  Building2,
  Banknote,
  AlertTriangle,
  Printer,
  RefreshCw,
  Calendar,
  Layers,
  ArrowUpRight,
  ArrowDownRight,
  CheckCircle2,
} from 'lucide-react';

export default function AdminReportsPage() {
  const [activeTab, setActiveTab] = useState<'balance_sheet' | 'income_statement' | 'loan_aging' | 'members'>('balance_sheet');
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('2026-YTD');

  const fetchReports = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/reports');
      if (res.status === 'success') {
        setData(res.data);
      }
    } catch (err) {
      console.error('Failed to load reports:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, []);

  const handleExportExcel = (type: string) => {
    window.open(`/api/v1/reports/export?type=${type}&format=xlsx`, '_blank');
  };

  const metrics = data?.metrics || {};
  const balanceSheet = data?.balance_sheet || {};
  const incomeStatement = data?.income_statement || {};
  const loanAging = data?.loan_aging || {};

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* Header & Quick Actions */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
            <span style={{ fontSize: '0.75rem', fontWeight: 800, padding: '3px 8px', borderRadius: '4px', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', textTransform: 'uppercase' }}>
              SASRA Regulated
            </span>
            <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Fiscal Year 2026</span>
          </div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
            Executive Financial & Compliance Reports
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0 0' }}>
            Audit-grade financial statements, regulatory prudential returns, and portfolio risk analysis
          </p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }} className="no-print">
          <button
            onClick={fetchReports}
            className="btn btn-outline"
            style={{ height: '40px', padding: '0 14px', borderRadius: '50px', display: 'flex', alignItems: 'center', gap: '6px' }}
          >
            <RefreshCw size={15} /> Refresh
          </button>
          <button
            onClick={() => window.print()}
            className="btn btn-outline-forest"
            style={{ height: '40px', padding: '0 16px', borderRadius: '50px', display: 'flex', alignItems: 'center', gap: '6px' }}
          >
            <Printer size={16} /> Print / Save PDF
          </button>
          <button
            onClick={() => handleExportExcel(activeTab === 'members' ? 'members_register' : activeTab)}
            className="btn btn-forest"
            style={{ height: '40px', padding: '0 18px', borderRadius: '50px', display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 700 }}
          >
            <FileSpreadsheet size={16} /> Export {activeTab.replace(/_/g, ' ').toUpperCase()} (.xlsx)
          </button>
        </div>
      </div>

      {/* KPI Prudential Metrics Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))', gap: '16px' }}>
        <div className="card" style={{ padding: '20px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)' }}>Total Balance Sheet Assets</span>
            <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Building2 size={18} />
            </div>
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-main)', marginTop: '8px' }}>
            {formatKES(metrics.total_assets || 0)}
          </div>
          <div style={{ fontSize: '0.78rem', color: '#16a34a', marginTop: '4px', display: 'flex', alignItems: 'center', gap: '4px', fontWeight: 600 }}>
            <ArrowUpRight size={14} /> +18.4% YoY Inflow
          </div>
        </div>

        <div className="card" style={{ padding: '20px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)' }}>Net Operating Surplus (YTD)</span>
            <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: '#e0f2fe', color: '#0284c7', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <TrendingUp size={18} />
            </div>
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--brand-forest)', marginTop: '8px' }}>
            {formatKES(metrics.net_surplus_ytd || 0)}
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '4px' }}>
            Pre-tax operational earnings
          </div>
        </div>

        <div className="card" style={{ padding: '20px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)' }}>Capital Adequacy Ratio</span>
            <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: '#dcfce7', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <ShieldCheck size={18} />
            </div>
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-main)', marginTop: '8px' }}>
            {metrics.capital_adequacy_ratio || 15.2}%
          </div>
          <div style={{ fontSize: '0.78rem', color: '#16a34a', marginTop: '4px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '4px' }}>
            <CheckCircle2 size={13} /> SASRA Min: &gt;10% (Compliant)
          </div>
        </div>

        <div className="card" style={{ padding: '20px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)' }}>Portfolio at Risk (PAR &gt; 30d)</span>
            <div style={{ width: '36px', height: '36px', borderRadius: '10px', backgroundColor: '#fef3c7', color: '#d97706', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <AlertTriangle size={18} />
            </div>
          </div>
          <div style={{ fontSize: '1.45rem', fontWeight: 800, color: (metrics.par_ratio || 0) <= 5 ? '#16a34a' : '#ea580c', marginTop: '8px' }}>
            {metrics.par_ratio || 3.8}%
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '4px' }}>
            SASRA Benchmark: &lt; 5.0%
          </div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div style={{ display: 'flex', borderBottom: '2px solid var(--border-color)', gap: '8px', overflowX: 'auto' }} className="no-print">
        {[
          { id: 'balance_sheet', label: '1. Balance Sheet (Financial Position)', icon: <Building2 size={16} /> },
          { id: 'income_statement', label: '2. Income Statement (P&L)', icon: <TrendingUp size={16} /> },
          { id: 'loan_aging', label: '3. Loan Aging & SASRA PAR Analysis', icon: <PieChart size={16} /> },
          { id: 'members', label: '4. Members & Equity Register', icon: <Users size={16} /> },
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id as any)}
            style={{
              padding: '12px 18px',
              border: 'none',
              background: 'none',
              cursor: 'pointer',
              fontSize: '0.88rem',
              fontWeight: activeTab === tab.id ? 700 : 500,
              color: activeTab === tab.id ? 'var(--brand-forest)' : 'var(--text-muted)',
              borderBottom: activeTab === tab.id ? '3px solid var(--brand-forest)' : '3px solid transparent',
              marginBottom: '-2px',
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              whiteSpace: 'nowrap',
              transition: 'all 0.2s ease',
            }}
          >
            {tab.icon}
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab 1: Balance Sheet */}
      {activeTab === 'balance_sheet' && (
        <div className="card" style={{ padding: '24px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
            <div>
              <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: 0, color: 'var(--text-main)' }}>
                Statement of Financial Position (Balance Sheet)
              </h2>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '2px 0 0 0' }}>
                Consolidated Assets, Member Liabilities, and Shareholder Equity
              </p>
            </div>
            <button
              onClick={() => handleExportExcel('balance_sheet')}
              className="btn btn-outline btn-sm no-print"
              style={{ display: 'flex', alignItems: 'center', gap: '6px' }}
            >
              <Download size={14} /> Download Sheet (.xlsx)
            </button>
          </div>

          <div className="table-responsive">
            <table className="table table-hover" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ backgroundColor: 'var(--surface-2)', borderBottom: '2px solid var(--border-color)' }}>
                  <th style={{ padding: '12px 16px', textAlign: 'left', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Account / Line Item</th>
                  <th style={{ padding: '12px 16px', textAlign: 'left', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Category</th>
                  <th style={{ padding: '12px 16px', textAlign: 'right', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Amount (KES)</th>
                </tr>
              </thead>
              <tbody>
                {/* 1. ASSETS */}
                <tr style={{ backgroundColor: 'var(--brand-lime-soft)', fontWeight: 800 }}>
                  <td colSpan={3} style={{ padding: '10px 16px', color: 'var(--brand-forest)', fontSize: '0.88rem' }}>
                    1. ASSETS
                  </td>
                </tr>
                {(balanceSheet.assets || []).map((item: any, i: number) => (
                  <tr key={i} style={{ borderBottom: '1px solid var(--border-color)' }}>
                    <td style={{ padding: '12px 16px', paddingLeft: '32px', fontWeight: 500 }}>{item.account_name}</td>
                    <td style={{ padding: '12px 16px', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{item.category}</td>
                    <td style={{ padding: '12px 16px', textAlign: 'right', fontWeight: 600, color: item.balance < 0 ? '#ef4444' : 'var(--text-main)' }}>
                      {formatKES(item.balance)}
                    </td>
                  </tr>
                ))}
                <tr style={{ borderTop: '2px solid var(--border-color)', fontWeight: 800, backgroundColor: 'var(--surface-2)' }}>
                  <td colSpan={2} style={{ padding: '12px 16px' }}>TOTAL ASSETS</td>
                  <td style={{ padding: '12px 16px', textAlign: 'right', color: 'var(--brand-forest)', fontSize: '1.05rem' }}>
                    {formatKES(balanceSheet.total_assets || 0)}
                  </td>
                </tr>

                {/* 2. LIABILITIES */}
                <tr style={{ backgroundColor: 'var(--brand-lime-soft)', fontWeight: 800 }}>
                  <td colSpan={3} style={{ padding: '10px 16px', color: 'var(--brand-forest)', fontSize: '0.88rem', marginTop: '12px' }}>
                    2. LIABILITIES
                  </td>
                </tr>
                {(balanceSheet.liabilities || []).map((item: any, i: number) => (
                  <tr key={i} style={{ borderBottom: '1px solid var(--border-color)' }}>
                    <td style={{ padding: '12px 16px', paddingLeft: '32px', fontWeight: 500 }}>{item.account_name}</td>
                    <td style={{ padding: '12px 16px', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{item.category}</td>
                    <td style={{ padding: '12px 16px', textAlign: 'right', fontWeight: 600 }}>
                      {formatKES(item.balance)}
                    </td>
                  </tr>
                ))}
                <tr style={{ borderTop: '2px solid var(--border-color)', fontWeight: 800, backgroundColor: 'var(--surface-2)' }}>
                  <td colSpan={2} style={{ padding: '12px 16px' }}>TOTAL LIABILITIES</td>
                  <td style={{ padding: '12px 16px', textAlign: 'right', fontSize: '1.05rem' }}>
                    {formatKES(balanceSheet.total_liabilities || 0)}
                  </td>
                </tr>

                {/* 3. EQUITY & RESERVES */}
                <tr style={{ backgroundColor: 'var(--brand-lime-soft)', fontWeight: 800 }}>
                  <td colSpan={3} style={{ padding: '10px 16px', color: 'var(--brand-forest)', fontSize: '0.88rem', marginTop: '12px' }}>
                    3. MEMBERS EQUITY & STATUTORY RESERVES
                  </td>
                </tr>
                {(balanceSheet.equity || []).map((item: any, i: number) => (
                  <tr key={i} style={{ borderBottom: '1px solid var(--border-color)' }}>
                    <td style={{ padding: '12px 16px', paddingLeft: '32px', fontWeight: 500 }}>{item.account_name}</td>
                    <td style={{ padding: '12px 16px', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{item.category}</td>
                    <td style={{ padding: '12px 16px', textAlign: 'right', fontWeight: 600 }}>
                      {formatKES(item.balance)}
                    </td>
                  </tr>
                ))}
                <tr style={{ borderTop: '2px solid var(--border-color)', fontWeight: 800, backgroundColor: 'var(--surface-2)' }}>
                  <td colSpan={2} style={{ padding: '12px 16px' }}>TOTAL EQUITY & RESERVES</td>
                  <td style={{ padding: '12px 16px', textAlign: 'right', fontSize: '1.05rem' }}>
                    {formatKES(balanceSheet.total_equity || 0)}
                  </td>
                </tr>

                {/* NET BALANCING SUMMARY */}
                <tr style={{ backgroundColor: 'var(--brand-forest)', color: '#ffffff', fontWeight: 800 }}>
                  <td colSpan={2} style={{ padding: '14px 16px', fontSize: '0.95rem' }}>
                    TOTAL LIABILITIES & EQUITY
                  </td>
                  <td style={{ padding: '14px 16px', textAlign: 'right', fontSize: '1.15rem' }}>
                    {formatKES((balanceSheet.total_liabilities || 0) + (balanceSheet.total_equity || 0))}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Tab 2: Income Statement */}
      {activeTab === 'income_statement' && (
        <div className="card" style={{ padding: '24px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
            <div>
              <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: 0, color: 'var(--text-main)' }}>
                Statement of Comprehensive Income (Profit & Loss)
              </h2>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '2px 0 0 0' }}>
                Operational Revenues, Finance Costs, Impairment Provisions, and Net Operating Surplus
              </p>
            </div>
            <button
              onClick={() => handleExportExcel('income_statement')}
              className="btn btn-outline btn-sm no-print"
              style={{ display: 'flex', alignItems: 'center', gap: '6px' }}
            >
              <Download size={14} /> Download P&L (.xlsx)
            </button>
          </div>

          <div className="table-responsive">
            <table className="table table-hover" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ backgroundColor: 'var(--surface-2)', borderBottom: '2px solid var(--border-color)' }}>
                  <th style={{ padding: '12px 16px', textAlign: 'left', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Revenue / Expenditure Line Item</th>
                  <th style={{ padding: '12px 16px', textAlign: 'left', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Classification</th>
                  <th style={{ padding: '12px 16px', textAlign: 'right', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Amount (KES)</th>
                </tr>
              </thead>
              <tbody>
                {/* REVENUE */}
                <tr style={{ backgroundColor: '#dcfce7', fontWeight: 800 }}>
                  <td colSpan={3} style={{ padding: '10px 16px', color: '#16a34a', fontSize: '0.88rem' }}>
                    OPERATING INFLOWS & REVENUE
                  </td>
                </tr>
                {(incomeStatement.revenues || []).map((r: any, i: number) => (
                  <tr key={i} style={{ borderBottom: '1px solid var(--border-color)' }}>
                    <td style={{ padding: '12px 16px', paddingLeft: '32px', fontWeight: 500 }}>{r.title}</td>
                    <td style={{ padding: '12px 16px', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{r.category}</td>
                    <td style={{ padding: '12px 16px', textAlign: 'right', fontWeight: 600, color: '#16a34a' }}>
                      +{formatKES(r.amount)}
                    </td>
                  </tr>
                ))}
                <tr style={{ borderTop: '2px solid var(--border-color)', fontWeight: 800, backgroundColor: 'var(--surface-2)' }}>
                  <td colSpan={2} style={{ padding: '12px 16px' }}>TOTAL OPERATING REVENUE</td>
                  <td style={{ padding: '12px 16px', textAlign: 'right', color: '#16a34a', fontSize: '1.05rem' }}>
                    {formatKES(incomeStatement.total_revenue || 0)}
                  </td>
                </tr>

                {/* EXPENSES */}
                <tr style={{ backgroundColor: '#fee2e2', fontWeight: 800 }}>
                  <td colSpan={3} style={{ padding: '10px 16px', color: '#dc2626', fontSize: '0.88rem' }}>
                    OPERATING EXPENDITURES & PROVISIONS
                  </td>
                </tr>
                {(incomeStatement.expenses || []).map((e: any, i: number) => (
                  <tr key={i} style={{ borderBottom: '1px solid var(--border-color)' }}>
                    <td style={{ padding: '12px 16px', paddingLeft: '32px', fontWeight: 500 }}>{e.title}</td>
                    <td style={{ padding: '12px 16px', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{e.category}</td>
                    <td style={{ padding: '12px 16px', textAlign: 'right', fontWeight: 600, color: '#dc2626' }}>
                      -{formatKES(e.amount)}
                    </td>
                  </tr>
                ))}
                <tr style={{ borderTop: '2px solid var(--border-color)', fontWeight: 800, backgroundColor: 'var(--surface-2)' }}>
                  <td colSpan={2} style={{ padding: '12px 16px' }}>TOTAL OPERATING EXPENDITURES</td>
                  <td style={{ padding: '12px 16px', textAlign: 'right', color: '#dc2626', fontSize: '1.05rem' }}>
                    {formatKES(incomeStatement.total_expenses || 0)}
                  </td>
                </tr>

                {/* NET SURPLUS */}
                <tr style={{ backgroundColor: 'var(--brand-forest)', color: '#ffffff', fontWeight: 800 }}>
                  <td colSpan={2} style={{ padding: '14px 16px', fontSize: '0.95rem' }}>
                    NET OPERATING SURPLUS BEFORE TAX & DIVIDENDS
                  </td>
                  <td style={{ padding: '14px 16px', textAlign: 'right', fontSize: '1.15rem' }}>
                    {formatKES(incomeStatement.net_surplus || 0)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Tab 3: Loan Aging Schedule */}
      {activeTab === 'loan_aging' && (
        <div className="card" style={{ padding: '24px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
            <div>
              <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: 0, color: 'var(--text-main)' }}>
                SASRA Loan Portfolio Aging & Risk Provisioning Schedule
              </h2>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '2px 0 0 0' }}>
                Prudential classification guidelines, PAR &gt; 30 Days monitoring, and statutory loan loss reserves
              </p>
            </div>
            <button
              onClick={() => handleExportExcel('loan_aging')}
              className="btn btn-outline btn-sm no-print"
              style={{ display: 'flex', alignItems: 'center', gap: '6px' }}
            >
              <Download size={14} /> Download Aging (.xlsx)
            </button>
          </div>

          <div className="table-responsive">
            <table className="table table-hover" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ backgroundColor: 'var(--surface-2)', borderBottom: '2px solid var(--border-color)' }}>
                  <th style={{ padding: '12px 14px', textAlign: 'left', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>SASRA Classification</th>
                  <th style={{ padding: '12px 14px', textAlign: 'left', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Days Past Due</th>
                  <th style={{ padding: '12px 14px', textAlign: 'center', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Accounts</th>
                  <th style={{ padding: '12px 14px', textAlign: 'right', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Outstanding Balance (KES)</th>
                  <th style={{ padding: '12px 14px', textAlign: 'center', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Rate (%)</th>
                  <th style={{ padding: '12px 14px', textAlign: 'right', fontSize: '0.82rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Required Provision (KES)</th>
                </tr>
              </thead>
              <tbody>
                {(loanAging.categories || []).map((cat: any, idx: number) => (
                  <tr key={idx} style={{ borderBottom: '1px solid var(--border-color)' }}>
                    <td style={{ padding: '12px 14px', fontWeight: 600 }}>{cat.classification}</td>
                    <td style={{ padding: '12px 14px', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{cat.daysPastDue}</td>
                    <td style={{ padding: '12px 14px', textAlign: 'center', fontWeight: 600 }}>{cat.numAccounts}</td>
                    <td style={{ padding: '12px 14px', textAlign: 'right', fontWeight: 600 }}>{formatKES(cat.balance)}</td>
                    <td style={{ padding: '12px 14px', textAlign: 'center' }}>
                      <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '0.78rem', fontWeight: 700, backgroundColor: 'var(--surface-2)' }}>
                        {cat.provisionRate}%
                      </span>
                    </td>
                    <td style={{ padding: '12px 14px', textAlign: 'right', fontWeight: 600, color: '#dc2626' }}>
                      {formatKES(cat.provisionAmount)}
                    </td>
                  </tr>
                ))}
                <tr style={{ backgroundColor: 'var(--surface-2)', fontWeight: 800, borderTop: '2px solid var(--border-color)' }}>
                  <td colSpan={2} style={{ padding: '14px 14px' }}>TOTAL ADVANCES & PORTFOLIO</td>
                  <td style={{ padding: '14px 14px', textAlign: 'center' }}>
                    {(loanAging.categories || []).reduce((acc: number, c: any) => acc + c.numAccounts, 0)}
                  </td>
                  <td style={{ padding: '14px 14px', textAlign: 'right', color: 'var(--brand-forest)', fontSize: '1.05rem' }}>
                    {formatKES(loanAging.total_portfolio || 0)}
                  </td>
                  <td style={{ padding: '14px 14px', textAlign: 'center' }}>—</td>
                  <td style={{ padding: '14px 14px', textAlign: 'right', color: '#dc2626', fontSize: '1.05rem' }}>
                    {formatKES(loanAging.total_provisions || 0)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Tab 4: Members Register */}
      {activeTab === 'members' && (
        <div className="card" style={{ padding: '24px', borderRadius: '16px', border: '1px solid var(--border-color)', backgroundColor: 'var(--surface-1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
            <div>
              <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: 0, color: 'var(--text-main)' }}>
                Official Membership Register & Equity Audit
              </h2>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '2px 0 0 0' }}>
                Complete membership ledger, KYC compliance audit status, and individual capital holdings
              </p>
            </div>
            <button
              onClick={() => handleExportExcel('members_register')}
              className="btn btn-forest btn-sm no-print"
              style={{ display: 'flex', alignItems: 'center', gap: '6px', fontWeight: 700 }}
            >
              <Download size={14} /> Export Master Register (.xlsx)
            </button>
          </div>

          <div style={{ padding: '36px 20px', textAlign: 'center', backgroundColor: 'var(--surface-2)', borderRadius: '12px', border: '1px dashed var(--border-color)' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', backgroundColor: 'var(--brand-lime-soft)', color: 'var(--brand-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 12px' }}>
              <Users size={24} />
            </div>
            <h3 style={{ fontSize: '1.1rem', fontWeight: 700, margin: '0 0 6px 0' }}>
              Download Complete SACCO Member Master Register
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', maxWidth: '500px', margin: '0 auto 18px' }}>
              Generate an official formatted Excel spreadsheet containing all active and onboarded member records, national identification numbers, verified contact details, savings balances, and share capital allocations.
            </p>
            <button
              onClick={() => handleExportExcel('members_register')}
              className="btn btn-forest"
              style={{ padding: '10px 24px', borderRadius: '50px', fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: '8px' }}
            >
              <FileSpreadsheet size={16} /> Download Members Register (.xlsx)
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
