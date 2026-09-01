'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate } from '@/lib/utils';
import {
  PieChart,
  Percent,
  Landmark,
  Coins,
  CheckCircle2,
  AlertCircle,
  Play,
  Sparkles,
  Users,
  ShieldCheck,
  RefreshCw,
  FileSpreadsheet,
} from 'lucide-react';

export default function AdminDividendsPage() {
  const { toast } = useToast();
  const [fiscalYear, setFiscalYear] = useState(new Date().getFullYear() - 1);
  const [totalPool, setTotalPool] = useState('1200000');
  const [whtRate, setWhtRate] = useState('15');
  const [loading, setLoading] = useState(false);
  const [simulationResult, setSimulationResult] = useState<any>(null);
  const [pastPeriods, setPastPeriods] = useState<any[]>([]);

  const runSimulation = async (dryRun = true) => {
    setLoading(true);
    setSimulationResult(null);

    try {
      const res = await api.post('/admin/declare_dividends', {
        fiscal_year: Number(fiscalYear),
        total_pool: parseFloat(totalPool) || undefined,
        whtRate: parseFloat(whtRate) / 100,
        dry_run: dryRun,
      });

      if (res.status === 'success') {
        setSimulationResult(res.data);
        if (dryRun) {
          toast.success('Dividend simulation completed successfully.');
        } else {
          toast.success('Dividends successfully distributed and credited to all active members!');
        }
      }
    } catch (err: any) {
      toast.error(err.message || 'Dividend execution failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ── Header ── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <div className="eyebrow-pill" style={{ marginBottom: '6px' }}>
            <span className="eyebrow-dot" /> Annual Surplus Distribution
          </div>
          <h1 style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
            Dividend Calculation & Payout Engine
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>
            Calculate weighted member dividends, apply 15% Withholding Tax (WHT), and execute ledger credits
          </p>
        </div>
      </div>

      {/* ── Form & Simulation Grid ── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: '24px' }}>
        {/* Declaration Controls */}
        <div className="card" style={{ padding: '28px', borderRadius: '20px' }}>
          <h3 style={{ fontSize: '1.15rem', fontWeight: 800, marginBottom: '18px' }}>
            Configure Fiscal Year Surplus
          </h3>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
            <div>
              <label className="input-label">Fiscal Year</label>
              <select
                className="form-select"
                value={fiscalYear}
                onChange={(e) => setFiscalYear(Number(e.target.value))}
              >
                <option value={2026}>FY 2026 (Current Operating Year)</option>
                <option value={2025}>FY 2025 (Previous Audit Year)</option>
                <option value={2024}>FY 2024</option>
              </select>
            </div>

            <div>
              <label className="input-label">Total Dividend Surplus Pool (KES)</label>
              <input
                type="number"
                min="10000"
                step="1000"
                className="input-control"
                value={totalPool}
                onChange={(e) => setTotalPool(e.target.value)}
                style={{ fontSize: '1.1rem', fontWeight: 700 }}
              />
            </div>

            <div>
              <label className="input-label">Withholding Tax (WHT %)</label>
              <input
                type="number"
                min="0"
                max="30"
                step="1"
                className="input-control"
                value={whtRate}
                onChange={(e) => setWhtRate(e.target.value)}
              />
              <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', marginTop: '4px', display: 'block' }}>
                Standard 15% WHT applied for SASRA regulated credit societies.
              </span>
            </div>

            <div style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
              <button
                type="button"
                onClick={() => runSimulation(true)}
                disabled={loading}
                className="btn btn-outline-forest"
                style={{ flex: 1 }}
              >
                <Play size={16} /> Run Dry-Run Simulation
              </button>
              <button
                type="button"
                onClick={() => {
                  if (window.confirm(`Execute live dividend payout for FY ${fiscalYear}? This will credit members' accounts and dispatch email notifications.`)) {
                    runSimulation(false);
                  }
                }}
                disabled={loading}
                className="btn btn-forest"
                style={{ flex: 1 }}
              >
                <CheckCircle2 size={16} /> Execute Payout
              </button>
            </div>
          </div>
        </div>

        {/* Live Simulation Output Box */}
        <div className="card" style={{ padding: '28px', borderRadius: '20px', backgroundColor: 'var(--surface-2)' }}>
          <h3 style={{ fontSize: '1.15rem', fontWeight: 800, marginBottom: '14px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <Sparkles size={20} color="var(--brand-forest)" /> Engine Simulation Output
          </h3>

          {loading ? (
            <div style={{ textAlign: 'center', padding: '40px', color: 'var(--text-muted)' }}>
              Calculating weighted shareholdings and net tax deductions...
            </div>
          ) : simulationResult ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <div
                style={{
                  padding: '16px',
                  borderRadius: '14px',
                  backgroundColor: 'var(--bg-surface)',
                  border: '1px solid var(--border-color)',
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>Target Fiscal Period:</span>
                  <strong>FY {simulationResult.details?.periodYear || fiscalYear}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>Total Declared Surplus:</span>
                  <strong>{formatKES(simulationResult.details?.totalPool || parseFloat(totalPool) || 0)}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>Eligible Member Beneficiaries:</span>
                  <strong>{simulationResult.recordsProcessed || simulationResult.details?.membersRewarded || 0} Members</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid var(--border-color)', paddingTop: '8px' }}>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>Net Payout Distributed:</span>
                  <strong style={{ color: '#16a34a', fontSize: '1.05rem' }}>
                    {formatKES(simulationResult.details?.totalDistributed || 0)}
                  </strong>
                </div>
              </div>

              <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)', lineHeight: 1.5 }}>
                Status: <strong style={{ color: 'var(--brand-forest)' }}>{simulationResult.message}</strong>
              </div>
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '48px 16px', color: 'var(--text-muted)' }}>
              <PieChart size={40} style={{ margin: '0 auto 12px', opacity: 0.5 }} />
              <div style={{ fontSize: '0.9rem', fontWeight: 600 }}>No simulation run yet.</div>
              <p style={{ fontSize: '0.8rem', marginTop: '4px' }}>
                Click <strong>Run Dry-Run Simulation</strong> to compute per-member allocations.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
