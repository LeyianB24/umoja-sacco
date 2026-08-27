'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatKES, formatDate } from '@/lib/utils';
import {
  Button,
  Card,
  Input,
  Badge,
  ListItem,
} from '@/components/sacco-ui';
import {
  Banknote,
  Plus,
  ArrowUp,
  ShieldCheck,
  Calendar,
  Check,
  ChevronRight,
  X,
  Smartphone,
  AlertCircle,
} from 'lucide-react';

export default function MemberLoansPage() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Apply Loan Wizard Modal (4 Steps)
  const [applyModal, setApplyModal] = useState(false);
  const [wizardStep, setWizardStep] = useState(1);
  const [amount, setAmount] = useState('20000');
  const [termMonths, setTermMonths] = useState(12);
  const [loanType, setLoanType] = useState('development');
  const [purpose, setPurpose] = useState('');
  const [agreeTerms, setAgreeTerms] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Repay Modal
  const [repayModal, setRepayModal] = useState(false);
  const [repayAmount, setRepayAmount] = useState('');
  const [repayPhone, setRepayPhone] = useState(user?.phone || '');
  const [repaying, setRepaying] = useState(false);

  const fetchLoans = async () => {
    try {
      const res = await api.get('/member/loans');
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
    fetchLoans();
  }, []);

  const interestRate = 0.10; // 10% per annum
  const principal = parseFloat(amount) || 0;
  const totalInterest = principal * (interestRate * (termMonths / 12));
  const totalRepayable = principal + totalInterest;
  const monthlyInstallment = termMonths > 0 ? totalRepayable / termMonths : 0;

  const handleApplyLoan = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreeTerms) {
      toast.error('Please agree to terms and conditions.');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/member/apply_loan', {
        amount: principal,
        loan_type: loanType,
        duration_months: termMonths,
        purpose,
      });
      toast.success('Loan application submitted successfully! Credit appraisal in progress.');
      setApplyModal(false);
      setWizardStep(1);
      setPurpose('');
      fetchLoans();
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Loan application failed.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleRepayLoan = async (e: React.FormEvent) => {
    e.preventDefault();
    const amt = parseFloat(repayAmount);
    if (isNaN(amt) || amt <= 0) return;

    setRepaying(true);
    try {
      await api.post('/member/mpesa_stk', {
        amount: amt,
        phone: repayPhone,
        type: 'loan_repayment',
      });
      toast.success('Repayment STK push sent to your phone. Enter your PIN.');
      setRepayModal(false);
      setRepayAmount('');
      fetchLoans();
      refreshUser();
    } catch (err: any) {
      toast.error(err.message || 'Repayment failed.');
    } finally {
      setRepaying(false);
    }
  };

  if (loading) {
    return (
      <div style={{ padding: '60px 0', textAlign: 'center', color: 'var(--color-gray-medium)' }}>
        <div
          style={{
            width: '36px',
            height: '36px',
            borderRadius: '50%',
            border: '3px solid var(--color-gray-border)',
            borderTopColor: 'var(--color-forest)',
            animation: 'spin 0.8s linear infinite',
            margin: '0 auto 16px',
          }}
        />
        <div>Loading Loans & Credit Facilities...</div>
      </div>
    );
  }

  const maxLimit = data?.loan_limit || 150000;
  const activeBal = data?.active_balance || 0;
  const availableCredit = Math.max(0, maxLimit - activeBal);
  const loansList = data?.loans || [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h1 className="heading-1" style={{ margin: 0 }}>Loans & Credit Facilities</h1>
          <p className="body-rg" style={{ margin: '4px 0 0 0' }}>
            Flexible working capital, asset finance, and emergency credit for drivers
          </p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          {activeBal > 0 && (
            <Button
              variant="secondary"
              size="md"
              pill
              onClick={() => setRepayModal(true)}
            >
              <ArrowUp size={16} strokeWidth={2.5} />
              <span>Make Repayment</span>
            </Button>
          )}
          <Button
            variant="primary"
            size="md"
            pill
            onClick={() => {
              setWizardStep(1);
              setApplyModal(true);
            }}
          >
            <Plus size={16} strokeWidth={2.5} />
            <span>Apply for Loan</span>
          </Button>
        </div>
      </div>

      {/* Metrics Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: activeBal > 0 ? 'var(--color-error-light)' : 'var(--color-lime-light)', color: activeBal > 0 ? 'var(--color-error)' : 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Banknote size={20} />
            </div>
            <Badge status={activeBal > 0 ? 'pending' : 'approved'}>
              {activeBal > 0 ? 'Active Loan' : 'Clear'}
            </Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Outstanding Balance</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: activeBal > 0 ? 'var(--color-error)' : 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatKES(activeBal)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Total principal and pending interest
          </div>
        </Card>

        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-lime-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <ShieldCheck size={20} />
            </div>
            <Badge status="approved">Multiplier: 3x</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Approved Borrowing Limit</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-charcoal)', marginTop: '2px' }}>
            {formatKES(maxLimit)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Based on 3x your cumulative savings
          </div>
        </Card>

        <Card variant="default">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: 'var(--color-gray-light)', color: 'var(--color-forest)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Calendar size={20} />
            </div>
            <Badge variant="info">Instant</Badge>
          </div>
          <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500 }}>Available Headroom</div>
          <div style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-forest)', marginTop: '2px' }}>
            {formatKES(availableCredit)}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px' }}>
            Ready for instant application
          </div>
        </Card>
      </div>

      {/* Loans History / List */}
      <Card variant="default">
        <Card.Header>
          <div>
            <h3 className="heading-2" style={{ fontSize: '18px', margin: 0 }}>My Loan History</h3>
            <p className="body-sm" style={{ margin: '2px 0 0 0' }}>All active, disbursed, and completed loans</p>
          </div>
        </Card.Header>
        <Card.Body>
          {loansList.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {loansList.map((loan: any, idx: number) => (
                <ListItem
                  key={idx}
                  icon={<Banknote size={18} color="var(--color-forest)" />}
                  label={`${loan.loan_type?.toUpperCase() || 'DEVELOPMENT'} LOAN — #${loan.loan_no || loan.id}`}
                  time={`Applied: ${formatDate(loan.created_at)} • Term: ${loan.repayment_period_months || 12} mo • Rate: 10%`}
                  amount={formatKES(loan.principal_amount || loan.amount)}
                  badge={<Badge status={loan.status || 'pending'} />}
                />
              ))}
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '36px', color: 'var(--color-gray-medium)', fontSize: '14px' }}>
              You have no loan history. Tap &quot;Apply for Loan&quot; above to get started.
            </div>
          )}
        </Card.Body>
      </Card>

      {/* ──────────────────────────────────────────────────────────────────
          LOAN APPLICATION 4-STEP WIZARD MODAL (Flow 2 from Brief)
          ────────────────────────────────────────────────────────────────── */}
      {applyModal && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 1060,
            backgroundColor: 'rgba(0, 0, 0, 0.45)',
            backdropFilter: 'blur(4px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
          onClick={() => setApplyModal(false)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              width: '100%',
              maxWidth: '460px',
              backgroundColor: 'var(--color-white)',
              borderRadius: 'var(--radius-lg)',
              padding: '24px',
              boxShadow: 'var(--shadow-lg)',
            }}
          >
            {/* Wizard Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px' }}>
              <div>
                <div style={{ fontSize: '12px', fontWeight: 600, color: 'var(--color-forest)', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                  Step {wizardStep} of 4
                </div>
                <h3 className="heading-3" style={{ margin: '2px 0 0 0' }}>
                  {wizardStep === 1 && 'Select Loan Amount'}
                  {wizardStep === 2 && 'Repayment Term'}
                  {wizardStep === 3 && 'Purpose & Category'}
                  {wizardStep === 4 && 'Review & Submit'}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setApplyModal(false)}
                style={{ width: '32px', height: '32px', borderRadius: '50%', border: 'none', background: 'var(--color-gray-light)', color: 'var(--color-gray-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            {/* Step Progress Bar */}
            <div style={{ display: 'flex', gap: '4px', marginBottom: '20px' }}>
              {[1, 2, 3, 4].map((s) => (
                <div
                  key={s}
                  style={{
                    flex: 1,
                    height: '4px',
                    borderRadius: '2px',
                    backgroundColor: s <= wizardStep ? 'var(--color-forest)' : 'var(--color-gray-light)',
                    transition: 'background-color 0.2s',
                  }}
                />
              ))}
            </div>

            {/* STEP 1: AMOUNT */}
            {wizardStep === 1 && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <Input
                  label="Loan Amount (KES)"
                  type="number"
                  min="1000"
                  max={maxLimit}
                  step="1000"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="e.g. 50000"
                  helperText={`Maximum available credit limit: ${formatKES(maxLimit)}`}
                  required
                />

                {/* Quick Presets */}
                <div>
                  <label className="sacco-input-label">Quick Selection</label>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '6px' }}>
                    {['10000', '25000', '50000', '100000'].map((val) => (
                      <Button
                        key={val}
                        type="button"
                        variant={amount === val ? 'primary' : 'secondary'}
                        size="sm"
                        onClick={() => setAmount(val)}
                      >
                        {formatKES(parseInt(val))}
                      </Button>
                    ))}
                  </div>
                </div>

                <Button
                  type="button"
                  variant="primary"
                  size="lg"
                  pill
                  onClick={() => setWizardStep(2)}
                  style={{ width: '100%', marginTop: '8px' }}
                >
                  Next: Select Term →
                </Button>
              </div>
            )}

            {/* STEP 2: TERM */}
            {wizardStep === 2 && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <div>
                  <label className="sacco-input-label">Repayment Duration</label>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                    {[6, 12, 18, 24].map((mo) => (
                      <button
                        key={mo}
                        type="button"
                        onClick={() => setTermMonths(mo)}
                        style={{
                          padding: '14px',
                          borderRadius: 'var(--radius-lg)',
                          border: termMonths === mo ? '2px solid var(--color-forest)' : '1px solid var(--color-gray-border)',
                          backgroundColor: termMonths === mo ? 'var(--color-lime-light)' : 'var(--color-white)',
                          textAlign: 'left',
                          cursor: 'pointer',
                        }}
                      >
                        <div style={{ fontWeight: 600, fontSize: '15px', color: 'var(--color-charcoal)' }}>{mo} Months</div>
                        <div style={{ fontSize: '12px', color: 'var(--color-gray-dark)', marginTop: '2px' }}>
                          ~{formatKES(principal * (1 + 0.1 * (mo / 12)) / mo)}/mo
                        </div>
                      </button>
                    ))}
                  </div>
                </div>

                <div style={{ padding: '12px 14px', backgroundColor: 'var(--color-gray-light)', borderRadius: 'var(--radius-md)' }}>
                  <div style={{ fontSize: '12px', color: 'var(--color-gray-dark)' }}>
                    Interest Rate: <b>10% per annum (Compounded reducing balance)</b>
                  </div>
                </div>

                <div style={{ display: 'flex', gap: '10px', marginTop: '8px' }}>
                  <Button type="button" variant="secondary" size="lg" style={{ flex: 1 }} onClick={() => setWizardStep(1)}>
                    Back
                  </Button>
                  <Button type="button" variant="primary" size="lg" pill style={{ flex: 2 }} onClick={() => setWizardStep(3)}>
                    Next: Loan Purpose →
                  </Button>
                </div>
              </div>
            )}

            {/* STEP 3: PURPOSE */}
            {wizardStep === 3 && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <div>
                  <label className="sacco-input-label">Loan Product</label>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                    {[
                      { id: 'development', name: 'Development Loan' },
                      { id: 'emergency', name: 'Emergency Loan' },
                      { id: 'asset_finance', name: 'Vehicle & Asset Finance' },
                      { id: 'school_fees', name: 'School Fees Loan' },
                    ].map((t) => (
                      <Button
                        key={t.id}
                        type="button"
                        variant={loanType === t.id ? 'primary' : 'secondary'}
                        size="sm"
                        onClick={() => setLoanType(t.id)}
                      >
                        {t.name}
                      </Button>
                    ))}
                  </div>
                </div>

                <div>
                  <label className="sacco-input-label">Why do you need this loan?</label>
                  <textarea
                    value={purpose}
                    onChange={(e) => setPurpose(e.target.value)}
                    rows={3}
                    placeholder="Describe vehicle repairs, working capital, expansion..."
                    style={{
                      width: '100%',
                      padding: '10px 14px',
                      fontSize: '14px',
                      fontFamily: 'var(--font-sans)',
                      color: 'var(--color-charcoal)',
                      backgroundColor: 'var(--color-gray-light)',
                      border: '1px solid var(--color-gray-border)',
                      borderRadius: 'var(--radius-md)',
                      outline: 'none',
                    }}
                    required
                  />
                </div>

                <div style={{ display: 'flex', gap: '10px', marginTop: '8px' }}>
                  <Button type="button" variant="secondary" size="lg" style={{ flex: 1 }} onClick={() => setWizardStep(2)}>
                    Back
                  </Button>
                  <Button type="button" variant="primary" size="lg" pill style={{ flex: 2 }} onClick={() => setWizardStep(4)}>
                    Next: Review →
                  </Button>
                </div>
              </div>
            )}

            {/* STEP 4: REVIEW & SUBMIT */}
            {wizardStep === 4 && (
              <form onSubmit={handleApplyLoan} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <div style={{ padding: '16px', backgroundColor: 'var(--color-gray-light)', borderRadius: 'var(--radius-lg)', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}>
                    <span style={{ color: 'var(--color-gray-dark)' }}>Principal Amount:</span>
                    <span style={{ fontWeight: 600, color: 'var(--color-charcoal)' }}>{formatKES(principal)}</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}>
                    <span style={{ color: 'var(--color-gray-dark)' }}>Repayment Term:</span>
                    <span style={{ fontWeight: 600, color: 'var(--color-charcoal)' }}>{termMonths} Months</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}>
                    <span style={{ color: 'var(--color-gray-dark)' }}>Total Estimated Interest:</span>
                    <span style={{ fontWeight: 600, color: 'var(--color-charcoal)' }}>{formatKES(totalInterest)}</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px', paddingTop: '8px', borderTop: '1px solid var(--color-gray-border)' }}>
                    <span style={{ fontWeight: 600, color: 'var(--color-charcoal)' }}>Monthly Installment:</span>
                    <span style={{ fontWeight: 600, color: 'var(--color-forest)', fontSize: '15px' }}>{formatKES(monthlyInstallment)}</span>
                  </div>
                </div>

                <label style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '13px', color: 'var(--color-charcoal)', cursor: 'pointer' }}>
                  <input
                    type="checkbox"
                    checked={agreeTerms}
                    onChange={(e) => setAgreeTerms(e.target.checked)}
                    required
                  />
                  <span>I agree to the Umoja Sacco loan terms, appraisal policies, and monthly recovery schedule.</span>
                </label>

                <div style={{ display: 'flex', gap: '10px', marginTop: '8px' }}>
                  <Button type="button" variant="secondary" size="lg" style={{ flex: 1 }} onClick={() => setWizardStep(3)}>
                    Back
                  </Button>
                  <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    pill
                    disabled={submitting || !agreeTerms}
                    style={{ flex: 2 }}
                  >
                    {submitting ? 'Submitting...' : 'Submit Application'}
                  </Button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {/* Repay Loan Modal */}
      {repayModal && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 1060,
            backgroundColor: 'rgba(0, 0, 0, 0.45)',
            backdropFilter: 'blur(4px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
          }}
          onClick={() => setRepayModal(false)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              width: '100%',
              maxWidth: '420px',
              backgroundColor: 'var(--color-white)',
              borderRadius: 'var(--radius-lg)',
              padding: '24px',
              boxShadow: 'var(--shadow-lg)',
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <div>
                <h3 className="heading-3" style={{ margin: 0 }}>Repay Loan via M-Pesa</h3>
                <p className="body-sm" style={{ margin: '2px 0 0 0' }}>Instant loan balance reduction</p>
              </div>
              <button
                type="button"
                onClick={() => setRepayModal(false)}
                style={{ width: '32px', height: '32px', borderRadius: '50%', border: 'none', background: 'var(--color-gray-light)', color: 'var(--color-gray-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleRepayLoan} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              <Input
                label="Repayment Amount (KES)"
                type="number"
                min="100"
                step="100"
                value={repayAmount}
                onChange={(e) => setRepayAmount(e.target.value)}
                placeholder={`e.g. ${Math.min(activeBal, 5000)}`}
                helperText={`Outstanding Balance: ${formatKES(activeBal)}`}
                required
              />

              <Input
                label="M-Pesa Phone Number"
                type="tel"
                value={repayPhone}
                onChange={(e) => setRepayPhone(e.target.value)}
                placeholder="e.g. 0712345678"
                required
              />

              <Button
                type="submit"
                variant="primary"
                size="lg"
                pill
                disabled={repaying}
                style={{ width: '100%', marginTop: '8px' }}
              >
                {repaying ? 'Sending Prompt...' : `Pay ${formatKES(parseFloat(repayAmount) || 0)}`}
              </Button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
