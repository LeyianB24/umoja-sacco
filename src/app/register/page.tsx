'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import {
  User,
  ShieldCheck,
  Phone,
  Mail,
  Lock,
  Eye,
  EyeOff,
  Calendar,
  Briefcase,
  MapPin,
  Heart,
  FileText,
  ArrowRight,
  ArrowLeft,
  AlertCircle,
  CheckCircle2,
  Sparkles,
  Building,
} from 'lucide-react';

export default function QuickRegisterWizard() {
  const router = useRouter();
  const { login } = useAuth();
  const { toast } = useToast();

  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [successData, setSuccessData] = useState<any>(null);

  // Form State
  const [formData, setFormData] = useState({
    email: '',
    phone: '',
    full_name: '',
    national_id: '',
    dob: '',
    gender: 'male',
    address: '',
    city: 'Nairobi',
    postal_code: '',
    occupation: 'Commercial Driver',
    nok_name: '',
    nok_phone: '',
    password: '',
    confirm_password: '',
    agree_terms: false,
  });

  const [fieldErrors, setFieldErrors] = useState<{ [key: string]: string }>({});
  const [validatingField, setValidatingField] = useState<{ [key: string]: boolean }>({});
  const [showPassword, setShowPassword] = useState(false);

  // Restore saved draft
  useEffect(() => {
    try {
      const saved = localStorage.getItem('usms_register_draft');
      if (saved) {
        const parsed = JSON.parse(saved);
        setFormData((prev) => ({ ...prev, ...parsed }));
      }
    } catch (_) {}
  }, []);

  // Save draft on change
  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target;
    const val = type === 'checkbox' ? (e.target as HTMLInputElement).checked : value;
    const next = { ...formData, [name]: val };
    setFormData(next);
    localStorage.setItem('usms_register_draft', JSON.stringify(next));

    // Clear error for field
    if (fieldErrors[name]) {
      setFieldErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  // Real-time validation on blur
  const validateField = async (field: 'email' | 'phone' | 'national_id') => {
    const val = (formData as any)[field];
    if (!val) return;

    setValidatingField((prev) => ({ ...prev, [field]: true }));
    try {
      const res = await api.post('/auth/validate', { field, value: val });
      if (res.status === 'success' && !res.data.available) {
        setFieldErrors((prev) => ({ ...prev, [field]: res.data.error || 'Invalid value' }));
      } else {
        setFieldErrors((prev) => ({ ...prev, [field]: '' }));
      }
    } catch (_) {
    } finally {
      setValidatingField((prev) => ({ ...prev, [field]: false }));
    }
  };

  // Calculate age
  const getAge = (dobString: string) => {
    if (!dobString) return 0;
    const birth = new Date(dobString);
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    const m = now.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) {
      age--;
    }
    return age;
  };

  // Step 1 Validation
  const validateStep1 = async () => {
    const errors: { [key: string]: string } = {};
    if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      errors.email = 'Please provide a valid email address.';
    }
    if (!formData.phone) {
      errors.phone = 'Phone number is required (e.g. 0712 345 678).';
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return false;
    }

    // Verify uniqueness
    try {
      const emailCheck = await api.post('/auth/validate', { field: 'email', value: formData.email });
      if (!emailCheck.data?.available) {
        setFieldErrors((prev) => ({ ...prev, email: emailCheck.data?.error || 'Email already registered.' }));
        return false;
      }
      const phoneCheck = await api.post('/auth/validate', { field: 'phone', value: formData.phone });
      if (!phoneCheck.data?.available) {
        setFieldErrors((prev) => ({ ...prev, phone: phoneCheck.data?.error || 'Phone already in use.' }));
        return false;
      }
    } catch (_) {}

    return true;
  };

  // Step 2 Validation
  const validateStep2 = async () => {
    const errors: { [key: string]: string } = {};
    if (!formData.full_name || formData.full_name.trim().length < 3) {
      errors.full_name = 'Full official name as per ID is required.';
    }
    if (!formData.national_id || !/^\d{7,8}$/.test(formData.national_id)) {
      errors.national_id = 'National ID must be 7 or 8 digits.';
    }
    if (!formData.dob) {
      errors.dob = 'Date of birth is required.';
    } else if (getAge(formData.dob) < 18) {
      errors.dob = 'You must be at least 18 years old to join Umoja SACCO.';
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return false;
    }

    try {
      const idCheck = await api.post('/auth/validate', { field: 'national_id', value: formData.national_id });
      if (!idCheck.data?.available) {
        setFieldErrors((prev) => ({ ...prev, national_id: idCheck.data?.error || 'National ID already in use.' }));
        return false;
      }
    } catch (_) {}

    return true;
  };

  // Step 3 Validation
  const validateStep3 = () => {
    const errors: { [key: string]: string } = {};
    if (!formData.address || formData.address.trim().length < 3) {
      errors.address = 'Please enter your pickup stage / residential address.';
    }
    if (!formData.city) {
      errors.city = 'City / County is required.';
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return false;
    }
    return true;
  };

  // Step Navigation
  const handleNext = async () => {
    setError('');
    if (step === 1) {
      const valid = await validateStep1();
      if (valid) setStep(2);
    } else if (step === 2) {
      const valid = await validateStep2();
      if (valid) setStep(3);
    } else if (step === 3) {
      const valid = validateStep3();
      if (valid) setStep(4);
    }
  };

  const handleBack = () => {
    setError('');
    if (step > 1) setStep(step - 1);
  };

  // Final Submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.agree_terms) {
      setError('You must accept the Sacco Terms & Conditions to register.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const res = await api.post('/auth/register', formData);
      localStorage.removeItem('usms_register_draft');
      toast.success('Registration successful! Welcome to Umoja SACCO.');
      setSuccessData(res.data);
    } catch (err: any) {
      setError(err.message || 'Registration failed. Please review your details.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'stretch',
        backgroundColor: '#0B1E17',
      }}
    >
      {/* ── Left Branding Panel ── */}
      <div
        style={{
          width: '380px',
          minWidth: '380px',
          background: `linear-gradient(160deg, rgba(11,30,22,0.95) 0%, rgba(15,57,43,0.92) 60%, rgba(10,24,18,0.98) 100%), url('/assets/images/sacco3.jpg') center/cover no-repeat`,
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between',
          padding: '44px 40px',
          position: 'sticky',
          top: 0,
          height: '100vh',
          overflow: 'hidden',
          color: '#FFFFFF',
        }}
        className="d-none d-lg-flex"
      >
        {/* Brand */}
        <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <div
            style={{
              width: '44px',
              height: '44px',
              borderRadius: '12px',
              backgroundColor: '#FFFFFF',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              overflow: 'hidden',
              padding: '3px',
            }}
          >
            <img
              src="/assets/images/people_logo.png"
              alt="Umoja Sacco"
              style={{ width: '100%', height: '100%', objectFit: 'contain' }}
            />
          </div>
          <div>
            <div style={{ fontSize: '0.95rem', fontWeight: 800, color: '#FFFFFF', letterSpacing: '-0.2px' }}>
              UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
            </div>
            <div style={{ fontSize: '0.62rem', fontWeight: 700, color: 'rgba(255,255,255,0.45)', textTransform: 'uppercase', letterSpacing: '0.8px' }}>
              Drivers & Allied Society
            </div>
          </div>
        </Link>

        {/* Wizard Step Indicator in Sidebar */}
        <div>
          <div className="eyebrow-pill" style={{ marginBottom: '16px' }}>
            <span className="eyebrow-dot" /> Fast Member Onboarding (&lt; 2 mins)
          </div>

          <h1 style={{ fontSize: '2rem', fontWeight: 800, color: '#FFFFFF', letterSpacing: '-0.8px', lineHeight: 1.2, marginBottom: '16px' }}>
            Join Kenya's Premier <br />
            <span style={{ color: 'var(--brand-lime)' }}>Transport Sacco.</span>
          </h1>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '18px', marginTop: '28px' }}>
            {[
              { num: 1, title: 'Email & Phone', desc: 'Login credentials & SMS alerts' },
              { num: 2, title: 'Personal Info', desc: 'Name, National ID & Age check' },
              { num: 3, title: 'Address & City', desc: 'Location, route & occupation' },
              { num: 4, title: 'Terms & Activation', desc: 'Review, accept & instant access' },
            ].map((s) => {
              const isCurrent = step === s.num;
              const isDone = step > s.num || successData;
              return (
                <div key={s.num} style={{ display: 'flex', alignItems: 'center', gap: '14px', opacity: isCurrent ? 1 : isDone ? 0.85 : 0.45, transition: 'opacity 0.2s' }}>
                  <div
                    style={{
                      width: '34px',
                      height: '34px',
                      borderRadius: '10px',
                      backgroundColor: isDone ? 'var(--brand-lime)' : isCurrent ? 'rgba(208, 247, 100, 0.18)' : 'rgba(255,255,255,0.1)',
                      border: `1px solid ${isCurrent || isDone ? 'var(--brand-lime)' : 'rgba(255,255,255,0.2)'}`,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '0.82rem',
                      fontWeight: 800,
                      color: isDone ? '#0B2419' : isCurrent ? 'var(--brand-lime)' : '#FFFFFF',
                      flexShrink: 0,
                    }}
                  >
                    {isDone ? '✓' : s.num}
                  </div>
                  <div>
                    <div style={{ fontSize: '0.88rem', fontWeight: 700, color: isCurrent ? 'var(--brand-lime)' : '#FFFFFF' }}>
                      {s.title}
                    </div>
                    <div style={{ fontSize: '0.72rem', color: 'rgba(255,255,255,0.55)' }}>{s.desc}</div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Footer */}
        <div style={{ fontSize: '0.72rem', color: 'rgba(255,255,255,0.45)' }}>
          Built by <strong style={{ color: 'var(--brand-lime)' }}>Bezalel Technologies</strong> &bull; &copy; 2026
        </div>
      </div>

      {/* ── Right Wizard Area ── */}
      <div
        style={{
          flex: 1,
          backgroundColor: 'var(--bg-surface)',
          overflowY: 'auto',
          padding: '40px 32px',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'center',
          alignItems: 'center',
        }}
      >
        <div style={{ width: '100%', maxWidth: '540px' }}>
          {/* Top Step Bar (Mobile & Desktop) */}
          <div style={{ marginBottom: '28px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.8px', color: 'var(--brand-forest)' }}>
                Step {step} of 4: {step === 1 ? 'Contact' : step === 2 ? 'Personal Details' : step === 3 ? 'Location' : 'Confirm'}
              </span>
              <span style={{ fontSize: '0.82rem', fontWeight: 700, color: 'var(--text-muted)' }}>
                {Math.round((step / 4) * 100)}% Completed
              </span>
            </div>

            {/* Progress Bar */}
            <div style={{ width: '100%', height: '6px', borderRadius: '50px', backgroundColor: 'var(--border-color)', overflow: 'hidden' }}>
              <div
                style={{
                  height: '100%',
                  width: `${(step / 4) * 100}%`,
                  backgroundColor: 'var(--brand-forest)',
                  background: 'linear-gradient(90deg, #0b2419 0%, #a3e635 100%)',
                  transition: 'width 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
                }}
              />
            </div>
          </div>

          {/* Success Screen */}
          {successData ? (
            <div className="card" style={{ padding: '40px 32px', textAlign: 'center', borderRadius: '24px', boxShadow: 'var(--shadow-lg)' }}>
              <div
                style={{
                  width: '68px',
                  height: '68px',
                  borderRadius: '50%',
                  backgroundColor: 'rgba(163, 230, 53, 0.2)',
                  color: '#15803d',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 20px',
                }}
              >
                <CheckCircle2 size={40} />
              </div>

              <h2 style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--brand-forest)', marginBottom: '8px' }}>
                Account Created! Welcome, {formData.full_name}
              </h2>
              <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)', marginBottom: '24px' }}>
                Your membership record has been registered successfully.
              </p>

              <div
                style={{
                  padding: '16px 20px',
                  borderRadius: '16px',
                  backgroundColor: 'var(--surface-2)',
                  border: '1px solid var(--border-color)',
                  display: 'inline-block',
                  marginBottom: '28px',
                }}
              >
                <div style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-muted)' }}>
                  Your Official Member Number
                </div>
                <div style={{ fontSize: '1.4rem', fontWeight: 800, color: 'var(--brand-forest)', fontFamily: 'monospace', marginTop: '4px' }}>
                  {successData.memberNumber || 'UMS-2026-0001'}
                </div>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                <button
                  onClick={() => router.push('/member?welcome=1')}
                  className="btn btn-forest btn-lg"
                  style={{ width: '100%', padding: '15px' }}
                >
                  <span>Go to Member Dashboard</span>
                  <ArrowRight size={18} />
                </button>
                <button
                  onClick={() => {
                    navigator.clipboard?.writeText(window.location.origin + '/register');
                    toast.success('Referral link copied to clipboard!');
                  }}
                  className="btn btn-outline-forest"
                  style={{ width: '100%' }}
                >
                  <Sparkles size={16} /> Invite Fellow Drivers (Referral)
                </button>
              </div>
            </div>
          ) : (
            <div className="card" style={{ padding: '36px 32px', borderRadius: '24px', boxShadow: 'var(--shadow-md)' }}>
              {error && (
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    padding: '12px 16px',
                    borderRadius: '12px',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    border: '1px solid rgba(239, 68, 68, 0.25)',
                    color: '#dc2626',
                    fontSize: '0.85rem',
                    fontWeight: 600,
                    marginBottom: '20px',
                  }}
                >
                  <AlertCircle size={18} />
                  <span>{error}</span>
                </div>
              )}

              {/* ── STEP 1: Email & Phone ── */}
              {step === 1 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  <div>
                    <h2 style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-main)' }}>
                      Contact Information
                    </h2>
                    <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '2px' }}>
                      Enter your email and mobile number for account access & SMS prompts.
                    </p>
                  </div>

                  <div>
                    <label className="input-label">Email Address <span style={{ color: '#dc2626' }}>*</span></label>
                    <div style={{ position: 'relative' }}>
                      <input
                        type="email"
                        name="email"
                        className="input-control"
                        placeholder="name@email.com"
                        value={formData.email}
                        onChange={handleChange}
                        onBlur={() => validateField('email')}
                        required
                        autoFocus
                      />
                    </div>
                    {fieldErrors.email && (
                      <div style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '4px', fontWeight: 600 }}>
                        {fieldErrors.email}
                      </div>
                    )}
                  </div>

                  <div>
                    <label className="input-label">M-Pesa Phone Number <span style={{ color: '#dc2626' }}>*</span></label>
                    <input
                      type="tel"
                      name="phone"
                      className="input-control"
                      placeholder="07xx xxx xxx or +2547..."
                      value={formData.phone}
                      onChange={handleChange}
                      onBlur={() => validateField('phone')}
                      required
                    />
                    {fieldErrors.phone ? (
                      <div style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '4px', fontWeight: 600 }}>
                        {fieldErrors.phone}
                      </div>
                    ) : (
                      <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', marginTop: '4px', display: 'block' }}>
                        Used for M-Pesa STK push loan disbursements and daily savings.
                      </span>
                    )}
                  </div>
                </div>
              )}

              {/* ── STEP 2: Personal Information ── */}
              {step === 2 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
                  <div>
                    <h2 style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-main)' }}>
                      Personal Identity
                    </h2>
                    <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '2px' }}>
                      As required by SASRA regulations, please provide your official ID details.
                    </p>
                  </div>

                  <div>
                    <label className="input-label">Full Official Name <span style={{ color: '#dc2626' }}>*</span></label>
                    <input
                      type="text"
                      name="full_name"
                      className="input-control"
                      placeholder="As printed on National ID card"
                      value={formData.full_name}
                      onChange={handleChange}
                      required
                      autoFocus
                    />
                    {fieldErrors.full_name && (
                      <div style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '4px', fontWeight: 600 }}>
                        {fieldErrors.full_name}
                      </div>
                    )}
                  </div>

                  <div>
                    <label className="input-label">National ID Number <span style={{ color: '#dc2626' }}>*</span></label>
                    <input
                      type="text"
                      name="national_id"
                      className="input-control"
                      placeholder="8-digit ID number"
                      maxLength={8}
                      value={formData.national_id}
                      onChange={handleChange}
                      onBlur={() => validateField('national_id')}
                      required
                    />
                    {fieldErrors.national_id && (
                      <div style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '4px', fontWeight: 600 }}>
                        {fieldErrors.national_id}
                      </div>
                    )}
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                    <div>
                      <label className="input-label">Date of Birth <span style={{ color: '#dc2626' }}>*</span></label>
                      <input
                        type="date"
                        name="dob"
                        className="input-control"
                        value={formData.dob}
                        onChange={handleChange}
                        required
                      />
                      {fieldErrors.dob && (
                        <div style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '4px', fontWeight: 600 }}>
                          {fieldErrors.dob}
                        </div>
                      )}
                    </div>

                    <div>
                      <label className="input-label">Gender</label>
                      <select name="gender" className="form-select" value={formData.gender} onChange={handleChange}>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                      </select>
                    </div>
                  </div>
                </div>
              )}

              {/* ── STEP 3: Address & Occupation ── */}
              {step === 3 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
                  <div>
                    <h2 style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-main)' }}>
                      Address & Location
                    </h2>
                    <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '2px' }}>
                      Your home base, transport route, and operational city.
                    </p>
                  </div>

                  <div>
                    <label className="input-label">Operating / Residential Address <span style={{ color: '#dc2626' }}>*</span></label>
                    <input
                      type="text"
                      name="address"
                      className="input-control"
                      placeholder="e.g. Stage 46, Ruiru / Kangemi"
                      value={formData.address}
                      onChange={handleChange}
                      required
                      autoFocus
                    />
                    {fieldErrors.address && (
                      <div style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '4px', fontWeight: 600 }}>
                        {fieldErrors.address}
                      </div>
                    )}
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                    <div>
                      <label className="input-label">City / County <span style={{ color: '#dc2626' }}>*</span></label>
                      <input
                        type="text"
                        name="city"
                        className="input-control"
                        placeholder="e.g. Nairobi / Kiambu"
                        value={formData.city}
                        onChange={handleChange}
                        required
                      />
                    </div>
                    <div>
                      <label className="input-label">Postal / Area Code</label>
                      <input
                        type="text"
                        name="postal_code"
                        className="input-control"
                        placeholder="e.g. 00100"
                        value={formData.postal_code}
                        onChange={handleChange}
                      />
                    </div>
                  </div>

                  <div>
                    <label className="input-label">Primary Occupation</label>
                    <select name="occupation" className="form-select" value={formData.occupation} onChange={handleChange}>
                      <option value="Commercial Driver">Commercial Driver (Taxi / Matatu / Bus)</option>
                      <option value="Fleet Owner / Operator">Fleet Owner / Operator</option>
                      <option value="Delivery Rider">Boda Boda / Delivery Rider</option>
                      <option value="Allied Transport Staff">Allied Transport Professional</option>
                    </select>
                  </div>
                </div>
              )}

              {/* ── STEP 4: Review & Terms ── */}
              {step === 4 && (
                <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  <div>
                    <h2 style={{ fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-main)' }}>
                      Account Confirmation
                    </h2>
                    <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '2px' }}>
                      Review your details, set an optional password, and accept bylaws.
                    </p>
                  </div>

                  {/* Summary Card */}
                  <div style={{ padding: '16px', borderRadius: '16px', backgroundColor: 'var(--surface-2)', fontSize: '0.85rem' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                      <div>
                        <span style={{ color: 'var(--text-muted)', fontSize: '0.72rem', textTransform: 'uppercase' }}>Full Name</span>
                        <div style={{ fontWeight: 700 }}>{formData.full_name}</div>
                      </div>
                      <div>
                        <span style={{ color: 'var(--text-muted)', fontSize: '0.72rem', textTransform: 'uppercase' }}>National ID</span>
                        <div style={{ fontWeight: 700 }}>{formData.national_id}</div>
                      </div>
                      <div>
                        <span style={{ color: 'var(--text-muted)', fontSize: '0.72rem', textTransform: 'uppercase' }}>Email</span>
                        <div style={{ fontWeight: 700 }}>{formData.email}</div>
                      </div>
                      <div>
                        <span style={{ color: 'var(--text-muted)', fontSize: '0.72rem', textTransform: 'uppercase' }}>Phone</span>
                        <div style={{ fontWeight: 700 }}>{formData.phone}</div>
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="input-label">Password (Optional — temporary password generated if blank)</label>
                    <div style={{ position: 'relative' }}>
                      <input
                        type={showPassword ? 'text' : 'password'}
                        name="password"
                        className="input-control"
                        placeholder="Create password (min 6 chars)"
                        value={formData.password}
                        onChange={handleChange}
                        style={{ paddingRight: '42px' }}
                      />
                      <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
                      >
                        {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                      </button>
                    </div>
                  </div>

                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '10px', marginTop: '6px' }}>
                    <input
                      type="checkbox"
                      id="agree_terms"
                      name="agree_terms"
                      checked={formData.agree_terms}
                      onChange={handleChange}
                      style={{ width: '18px', height: '18px', marginTop: '2px', cursor: 'pointer', accentColor: 'var(--brand-forest)' }}
                    />
                    <label htmlFor="agree_terms" style={{ fontSize: '0.82rem', color: 'var(--text-main)', lineHeight: 1.4, cursor: 'pointer' }}>
                      I agree to the <Link href="/terms" target="_blank" style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>Umoja SACCO Bylaws & Terms</Link> and consent to credit verification under Kenyan cooperative law.
                    </label>
                  </div>

                  <button
                    type="submit"
                    disabled={loading || !formData.agree_terms}
                    className="btn btn-forest btn-lg"
                    style={{ width: '100%', padding: '15px' }}
                  >
                    {loading ? 'Creating Your Sacco Account...' : 'Complete & Create Account'}
                    {!loading && <ArrowRight size={18} />}
                  </button>
                </form>
              )}

              {/* Wizard Nav Controls (Steps 1, 2, 3) */}
              {step < 4 && (
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '28px', paddingTop: '20px', borderTop: '1px solid var(--border-color)' }}>
                  {step > 1 ? (
                    <button type="button" onClick={handleBack} className="btn btn-outline-forest">
                      <ArrowLeft size={16} /> Back
                    </button>
                  ) : (
                    <Link href="/login" style={{ fontSize: '0.85rem', color: 'var(--brand-forest)', fontWeight: 700 }}>
                      Sign In Instead
                    </Link>
                  )}

                  <button type="button" onClick={handleNext} className="btn btn-forest">
                    Next Step <ArrowRight size={16} />
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
