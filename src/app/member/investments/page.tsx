'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate } from '@/lib/utils';
import {
  TrendingUp,
  Plus,
  Building,
  LineChart,
  Landmark,
  Coins,
  Gem,
  DollarSign,
  Briefcase,
  PieChart,
  ArrowUpRight,
  ArrowDownRight,
  Sparkles,
  Calendar,
  X,
  Edit3,
  Trash2,
  CheckCircle2,
  RefreshCw,
  Clock,
  ArrowRight,
  ArrowLeft,
} from 'lucide-react';

export default function MemberInvestmentsPage() {
  const { toast } = useToast();
  const [data, setData] = useState<any>({
    investments: [],
    totalCost: 0,
    totalValue: 0,
    totalGainLoss: 0,
    totalGainLossPercent: 0,
  });
  const [loading, setLoading] = useState(true);

  // Wizard Modal State
  const [wizardOpen, setWizardOpen] = useState(false);
  const [wizardStep, setWizardStep] = useState(1);
  const [formType, setFormType] = useState('stock');
  const [assetName, setAssetName] = useState('');
  const [purchaseDate, setPurchaseDate] = useState(new Date().toISOString().split('T')[0]);
  const [quantity, setQuantity] = useState('1');
  const [costPrice, setCostPrice] = useState('');
  const [maturityDate, setMaturityDate] = useState('');
  const [expectedReturn, setExpectedReturn] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Price Update Modal State
  const [priceModalOpen, setPriceModalOpen] = useState(false);
  const [selectedInv, setSelectedInv] = useState<any>(null);
  const [newPrice, setNewPrice] = useState('');
  const [updatingPrice, setUpdatingPrice] = useState(false);

  const fetchInvestments = async () => {
    try {
      const res = await api.get('/member/investments');
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
    fetchInvestments();
  }, []);

  const resetWizard = () => {
    setWizardStep(1);
    setFormType('stock');
    setAssetName('');
    setPurchaseDate(new Date().toISOString().split('T')[0]);
    setQuantity('1');
    setCostPrice('');
    setMaturityDate('');
    setExpectedReturn('');
    setNotes('');
  };

  const handleAddInvestment = async () => {
    const cost = parseFloat(costPrice);
    if (!assetName || isNaN(cost) || cost <= 0) {
      toast.error('Please enter a valid asset name and cost.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await api.post('/member/investments', {
        type: formType,
        asset_name: assetName,
        purchase_date: purchaseDate,
        quantity: parseFloat(quantity) || 1,
        cost_price: cost,
        maturity_date: maturityDate || undefined,
        expected_return: expectedReturn ? parseFloat(expectedReturn) : undefined,
        notes,
      });

      toast.success(res.message || 'Investment added to your portfolio!');
      setWizardOpen(false);
      resetWizard();
      fetchInvestments();
    } catch (err: any) {
      toast.error(err.message || 'Failed to save investment.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleUpdatePrice = async (e: React.FormEvent) => {
    e.preventDefault();
    const p = parseFloat(newPrice);
    if (isNaN(p) || p <= 0) {
      toast.error('Please enter a valid unit price.');
      return;
    }

    setUpdatingPrice(true);
    try {
      await api.put('/member/investments', {
        investment_id: selectedInv.investment_id,
        current_price: p,
      });
      toast.success('Asset market price updated!');
      setPriceModalOpen(false);
      fetchInvestments();
    } catch (err: any) {
      toast.error(err.message || 'Failed to update price.');
    } finally {
      setUpdatingPrice(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this asset from your portfolio?')) return;
    try {
      await api.delete(`/member/investments?id=${id}`);
      toast.success('Investment removed.');
      fetchInvestments();
    } catch (err: any) {
      toast.error(err.message || 'Failed to delete investment.');
    }
  };

  const getAssetIcon = (type: string) => {
    switch (type) {
      case 'real_estate':
        return <Building size={22} color="#0B2419" />;
      case 'stock':
        return <LineChart size={22} color="#0B2419" />;
      case 'bonds':
      case 'fixed_deposit':
        return <Landmark size={22} color="#0B2419" />;
      case 'crypto':
        return <Coins size={22} color="#0B2419" />;
      case 'gold':
        return <Gem size={22} color="#0B2419" />;
      default:
        return <Briefcase size={22} color="#0B2419" />;
    }
  };

  const totalInvestmentCalc = (parseFloat(quantity) || 1) * (parseFloat(costPrice) || 0);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      {/* ── Header ── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <div className="eyebrow-pill" style={{ marginBottom: '6px' }}>
            <span className="eyebrow-dot" /> Wealth & Asset Management
          </div>
          <h1 style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
            My Investment Portfolio
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>
            Track your real estate, fixed deposits, equities, money markets, and precious assets
          </p>
        </div>

        <button
          onClick={() => {
            resetWizard();
            setWizardOpen(true);
          }}
          className="btn btn-forest btn-lg"
          style={{ borderRadius: '50px', padding: '12px 24px', boxShadow: '0 4px 14px rgba(11, 36, 25, 0.25)' }}
        >
          <Plus size={18} /> New Investment
        </button>
      </div>

      {/* ── Portfolio Overview Metrics ── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '20px' }}>
        {/* Total Value */}
        <div className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px' }}>
                Total Portfolio Value
              </span>
              <Sparkles size={18} color="var(--brand-forest)" />
            </div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--brand-forest)', letterSpacing: '-0.5px' }}>
              {formatKES(data.totalValue)}
            </div>
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '12px' }}>
            Across {data.investments.length} Active Asset Holding(s)
          </div>
        </div>

        {/* Invested Principal */}
        <div className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px' }}>
                Total Invested Capital
              </span>
              <Landmark size={18} color="var(--text-muted)" />
            </div>
            <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
              {formatKES(data.totalCost)}
            </div>
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '12px' }}>
            Initial Acquisition Cost Basis
          </div>
        </div>

        {/* Overall Net Gain / Loss */}
        <div className="card card-hover" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px' }}>
                Portfolio Net Return
              </span>
              <span className={`badge ${data.totalGainLoss >= 0 ? 'badge-success' : 'badge-danger'}`}>
                {data.totalGainLoss >= 0 ? '+' : ''}{data.totalGainLossPercent}%
              </span>
            </div>
            <div
              style={{
                fontSize: '2rem',
                fontWeight: 800,
                color: data.totalGainLoss >= 0 ? '#16a34a' : '#dc2626',
                letterSpacing: '-0.5px',
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
              }}
            >
              {data.totalGainLoss >= 0 ? <ArrowUpRight size={26} /> : <ArrowDownRight size={26} />}
              {formatKES(Math.abs(data.totalGainLoss))}
            </div>
          </div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '12px' }}>
            Net Unrealized Capital Appreciation
          </div>
        </div>
      </div>

      {/* ── Investments Grid Cards ── */}
      <div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
          <h2 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
            Asset Holdings ({data.investments.length})
          </h2>
          <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
            Daily accrued interest & market updates
          </span>
        </div>

        {loading ? (
          <div style={{ textAlign: 'center', padding: '60px', color: 'var(--text-muted)' }}>
            Loading your investment portfolio...
          </div>
        ) : data.investments.length > 0 ? (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '20px' }}>
            {data.investments.map((inv: any) => {
              const isProfit = (inv.gain_loss || 0) >= 0;
              return (
                <div
                  key={inv.investment_id}
                  className="card card-hover"
                  style={{
                    padding: '24px',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'space-between',
                    gap: '16px',
                  }}
                >
                  {/* Top Bar */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                      <div
                        style={{
                          width: '46px',
                          height: '46px',
                          borderRadius: '14px',
                          backgroundColor: 'rgba(163, 230, 53, 0.18)',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                        }}
                      >
                        {getAssetIcon(inv.type)}
                      </div>
                      <div>
                        <h3 style={{ fontSize: '1.05rem', fontWeight: 800, margin: 0, color: 'var(--text-main)' }}>
                          {inv.asset_name}
                        </h3>
                        <span className="badge badge-forest" style={{ textTransform: 'capitalize', marginTop: '4px' }}>
                          {inv.type.replace(/_/g, ' ')}
                        </span>
                      </div>
                    </div>

                    <div style={{ textAlign: 'right' }}>
                      <div
                        style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: '4px',
                          padding: '4px 8px',
                          borderRadius: '8px',
                          backgroundColor: isProfit ? 'rgba(22, 163, 74, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                          color: isProfit ? '#16a34a' : '#dc2626',
                          fontWeight: 800,
                          fontSize: '0.8rem',
                        }}
                      >
                        {isProfit ? '+' : ''}{inv.gain_loss_percent || 0}%
                      </div>
                    </div>
                  </div>

                  {/* Valuation Breakdown */}
                  <div
                    style={{
                      padding: '14px 16px',
                      borderRadius: '14px',
                      backgroundColor: 'var(--surface-2)',
                      display: 'grid',
                      gridTemplateColumns: '1fr 1fr',
                      gap: '12px',
                    }}
                  >
                    <div>
                      <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                        Cost Basis
                      </span>
                      <div style={{ fontWeight: 700, fontSize: '0.92rem', color: 'var(--text-muted)', marginTop: '2px' }}>
                        {formatKES(inv.cost_price)}
                      </div>
                    </div>
                    <div>
                      <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                        Current Valuation
                      </span>
                      <div style={{ fontWeight: 800, fontSize: '1.05rem', color: 'var(--brand-forest)', marginTop: '2px' }}>
                        {formatKES(inv.current_value)}
                      </div>
                    </div>
                  </div>

                  {/* Metadata: Expected Return / Maturity */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', color: 'var(--text-muted)' }}>
                    <span>Purchased: {formatDate(inv.purchase_date)}</span>
                    {inv.expected_return > 0 && (
                      <span style={{ fontWeight: 700, color: 'var(--brand-forest)' }}>
                        Yield: {inv.expected_return}% p.a.
                      </span>
                    )}
                  </div>

                  {/* Actions Bar */}
                  <div style={{ display: 'flex', gap: '8px', borderTop: '1px solid var(--border-color)', paddingTop: '12px' }}>
                    <button
                      onClick={() => {
                        setSelectedInv(inv);
                        setNewPrice(String(inv.current_price || inv.current_value));
                        setPriceModalOpen(true);
                      }}
                      className="btn btn-outline-forest"
                      style={{ flex: 1, fontSize: '0.78rem', padding: '6px' }}
                    >
                      <Edit3 size={14} /> Update Price
                    </button>
                    <button
                      onClick={() => handleDelete(inv.investment_id)}
                      className="btn btn-outline-forest"
                      style={{ color: '#dc2626', borderColor: 'rgba(239,68,68,0.3)', padding: '6px 12px' }}
                    >
                      <Trash2 size={14} />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="card" style={{ padding: '60px 24px', textAlign: 'center', borderRadius: '24px' }}>
            <Briefcase size={48} style={{ color: 'var(--brand-forest)', margin: '0 auto 16px', opacity: 0.7 }} />
            <h3 style={{ fontSize: '1.2rem', fontWeight: 800, marginBottom: '6px' }}>No Investments Added Yet</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', maxWidth: '440px', margin: '0 auto 20px' }}>
              Add stocks, commercial land plots, fixed deposit bank contracts, or crypto holdings to start tracking your real net worth.
            </p>
            <button
              onClick={() => {
                resetWizard();
                setWizardOpen(true);
              }}
              className="btn btn-forest"
            >
              <Plus size={16} /> Add Your First Investment
            </button>
          </div>
        )}
      </div>

      {/* ── 3-Step Add Investment Wizard Modal ── */}
      {wizardOpen && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0,0,0,0.65)',
            backdropFilter: 'blur(4px)',
            zIndex: 1100,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
        >
          <div
            className="card"
            style={{
              width: '100%',
              maxWidth: '520px',
              padding: '32px',
              borderRadius: '24px',
              backgroundColor: 'var(--bg-surface)',
              boxShadow: '0 25px 50px rgba(0,0,0,0.3)',
            }}
          >
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <div>
                <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--brand-forest)' }}>
                  Step {wizardStep} of 3
                </span>
                <h3 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-main)', margin: '2px 0 0' }}>
                  {wizardStep === 1 ? 'Select Investment Class' : wizardStep === 2 ? 'Asset Purchase Details' : 'Review & Confirm'}
                </h3>
              </div>
              <button
                onClick={() => setWizardOpen(false)}
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
              >
                <X size={20} />
              </button>
            </div>

            {/* STEP 1: Investment Type */}
            {wizardStep === 1 && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '10px' }}>
                  {[
                    { id: 'stock', label: 'Equities / Stocks', icon: <LineChart size={18} /> },
                    { id: 'real_estate', label: 'Real Estate / Land', icon: <Building size={18} /> },
                    { id: 'fixed_deposit', label: 'Fixed Deposit (Bank)', icon: <Landmark size={18} /> },
                    { id: 'bonds', label: 'Treasury Bonds / Bills', icon: <Landmark size={18} /> },
                    { id: 'crypto', label: 'Crypto (BTC / ETH)', icon: <Coins size={18} /> },
                    { id: 'gold', label: 'Precious Metals / Gold', icon: <Gem size={18} /> },
                    { id: 'mutual_fund', label: 'Money Market / Mutual', icon: <DollarSign size={18} /> },
                    { id: 'other', label: 'Other Business Asset', icon: <Briefcase size={18} /> },
                  ].map((t) => {
                    const isSelected = formType === t.id;
                    return (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() => setFormType(t.id)}
                        style={{
                          padding: '14px 12px',
                          borderRadius: '14px',
                          backgroundColor: isSelected ? 'rgba(163, 230, 53, 0.18)' : 'var(--surface-2)',
                          border: `2px solid ${isSelected ? 'var(--brand-forest)' : 'var(--border-color)'}`,
                          color: 'var(--text-main)',
                          display: 'flex',
                          flexDirection: 'column',
                          alignItems: 'center',
                          gap: '8px',
                          cursor: 'pointer',
                          textAlign: 'center',
                          transition: 'all 0.15s ease',
                        }}
                      >
                        <div style={{ color: isSelected ? 'var(--brand-forest)' : 'var(--text-muted)' }}>
                          {t.icon}
                        </div>
                        <span style={{ fontSize: '0.78rem', fontWeight: isSelected ? 800 : 600 }}>
                          {t.label}
                        </span>
                      </button>
                    );
                  })}
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '16px' }}>
                  <button onClick={() => setWizardStep(2)} className="btn btn-forest">
                    Next Step <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

            {/* STEP 2: Details */}
            {wizardStep === 2 && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <div>
                  <label className="input-label">Asset Name <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="text"
                    className="input-control"
                    placeholder="e.g. Safaricom PLC Shares / 0.5 Acre Kajiado Plot"
                    value={assetName}
                    onChange={(e) => setAssetName(e.target.value)}
                    required
                    autoFocus
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label className="input-label">Purchase Date</label>
                    <input
                      type="date"
                      className="input-control"
                      value={purchaseDate}
                      onChange={(e) => setPurchaseDate(e.target.value)}
                      required
                    />
                  </div>

                  <div>
                    <label className="input-label">Quantity / Units</label>
                    <input
                      type="number"
                      min="0.0001"
                      step="any"
                      className="input-control"
                      placeholder="1"
                      value={quantity}
                      onChange={(e) => setQuantity(e.target.value)}
                      required
                    />
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label className="input-label">Unit Cost (KES) <span style={{ color: '#dc2626' }}>*</span></label>
                    <input
                      type="number"
                      min="1"
                      className="input-control"
                      placeholder="e.g. 50,000"
                      value={costPrice}
                      onChange={(e) => setCostPrice(e.target.value)}
                      required
                    />
                  </div>

                  <div>
                    <label className="input-label">Expected Return (% p.a.)</label>
                    <input
                      type="number"
                      min="0"
                      max="100"
                      step="0.1"
                      className="input-control"
                      placeholder="e.g. 12%"
                      value={expectedReturn}
                      onChange={(e) => setExpectedReturn(e.target.value)}
                    />
                  </div>
                </div>

                <div>
                  <label className="input-label">Maturity Date (Optional for fixed deposits/bonds)</label>
                  <input
                    type="date"
                    className="input-control"
                    value={maturityDate}
                    onChange={(e) => setMaturityDate(e.target.value)}
                  />
                </div>

                <div>
                  <label className="input-label">Notes (Optional)</label>
                  <input
                    type="text"
                    className="input-control"
                    placeholder="e.g. Purchased through Dyer & Blair / Title Deed #4982"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                  />
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '12px' }}>
                  <button onClick={() => setWizardStep(1)} className="btn btn-outline-forest">
                    <ArrowLeft size={16} /> Back
                  </button>
                  <button
                    onClick={() => {
                      if (!assetName || !costPrice) {
                        toast.error('Asset name and cost are required.');
                        return;
                      }
                      setWizardStep(3);
                    }}
                    className="btn btn-forest"
                  >
                    Review & Confirm <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

            {/* STEP 3: Review & Submit */}
            {wizardStep === 3 && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
                <div style={{ padding: '18px', borderRadius: '16px', backgroundColor: 'var(--surface-2)', fontSize: '0.85rem' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                    <div>
                      <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Asset Name</span>
                      <div style={{ fontWeight: 800, fontSize: '1rem', color: 'var(--text-main)' }}>{assetName}</div>
                    </div>
                    <div>
                      <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Asset Class</span>
                      <div style={{ fontWeight: 700, textTransform: 'capitalize' }}>{formType.replace(/_/g, ' ')}</div>
                    </div>
                    <div>
                      <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Total Principal</span>
                      <div style={{ fontWeight: 800, color: 'var(--brand-forest)', fontSize: '1.1rem' }}>
                        {formatKES(totalInvestmentCalc)}
                      </div>
                      <small style={{ color: 'var(--text-muted)' }}>({quantity} units @ {formatKES(parseFloat(costPrice) || 0)})</small>
                    </div>
                    <div>
                      <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Expected Yield</span>
                      <div style={{ fontWeight: 700 }}>
                        {expectedReturn ? `${expectedReturn}% p.a.` : 'Capital Appreciation'}
                      </div>
                    </div>
                  </div>
                </div>

                <div style={{ display: 'flex', gap: '10px', marginTop: '8px' }}>
                  <button onClick={() => setWizardStep(2)} className="btn btn-outline-forest" style={{ flex: 1 }}>
                    <ArrowLeft size={16} /> Edit Details
                  </button>
                  <button
                    onClick={handleAddInvestment}
                    disabled={submitting}
                    className="btn btn-forest"
                    style={{ flex: 1 }}
                  >
                    {submitting ? 'Adding Asset...' : 'Confirm & Save Asset'}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Price Valuation Update Modal ── */}
      {priceModalOpen && selectedInv && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0,0,0,0.65)',
            backdropFilter: 'blur(4px)',
            zIndex: 1100,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
        >
          <div
            className="card"
            style={{
              width: '100%',
              maxWidth: '400px',
              padding: '28px',
              borderRadius: '24px',
              backgroundColor: 'var(--bg-surface)',
              boxShadow: '0 25px 50px rgba(0,0,0,0.3)',
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
              <div>
                <h3 style={{ fontSize: '1.15rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
                  Update Asset Valuation
                </h3>
                <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', margin: '2px 0 0' }}>
                  {selectedInv.asset_name}
                </p>
              </div>
              <button
                onClick={() => setPriceModalOpen(false)}
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
              >
                <X size={20} />
              </button>
            </div>

            <form onSubmit={handleUpdatePrice} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <div>
                <label className="input-label">New Current Market Price (KES)</label>
                <input
                  type="number"
                  min="0.01"
                  step="any"
                  className="input-control"
                  value={newPrice}
                  onChange={(e) => setNewPrice(e.target.value)}
                  required
                  autoFocus
                />
              </div>

              <div style={{ display: 'flex', gap: '10px' }}>
                <button type="button" onClick={() => setPriceModalOpen(false)} className="btn btn-outline-forest" style={{ flex: 1 }}>
                  Cancel
                </button>
                <button type="submit" disabled={updatingPrice} className="btn btn-forest" style={{ flex: 1 }}>
                  {updatingPrice ? 'Updating...' : 'Save New Value'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
