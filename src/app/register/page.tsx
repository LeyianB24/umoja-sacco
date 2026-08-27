'use client';

import React, { useState } from 'react';
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
  AlertCircle,
  Upload,
} from 'lucide-react';

export default function RegisterPage() {
  const router = useRouter();
  const { login } = useAuth();
  const { toast } = useToast();

  const [formData, setFormData] = useState({
    full_name: '',
    national_id: '',
    phone: '',
    email: '',
    password: '',
    confirm_password: '',
    gender: 'male',
    dob: '',
    occupation: '',
    address: '',
    nok_name: '',
    nok_phone: '',
  });

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (formData.password !== formData.confirm_password) {
      setError('Passwords do not match.');
      return;
    }
    if (formData.password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const res = await api.post('/public/register', formData);
      toast.success('Account created successfully! Welcome to Umoja Sacco.');
      // Auto-login or redirect
      if (res.status === 'success') {
        try {
          await login({ identifier: formData.email, password: formData.password, user_type: 'member' });
        } catch (_) {}
      }
      router.push('/member');
    } catch (err: any) {
      setError(err.message || 'Registration failed. Please check your details.');
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
          background: `linear-gradient(160deg, rgba(11,30,22,0.94) 0%, rgba(15,57,43,0.90) 60%, rgba(10,24,18,0.97) 100%), url('/assets/images/sacco3.jpg') center/cover no-repeat`,
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
              Member Portal
            </div>
          </div>
        </Link>

        {/* Hero Step Pitch */}
        <div>
          <div className="eyebrow-pill" style={{ marginBottom: '16px' }}>
            <span className="eyebrow-dot" /> New Member Onboarding
          </div>

          <h1 style={{ fontSize: '2.1rem', fontWeight: 800, color: '#FFFFFF', letterSpacing: '-0.8px', lineHeight: 1.15, marginBottom: '14px' }}>
            Join the <br />
            <span style={{ color: 'var(--brand-lime)' }}>Sacco</span> <br />
            community.
          </h1>

          <p style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.65)', lineHeight: 1.7, marginBottom: '28px' }}>
            Start your journey toward financial freedom. Simple registration, secure access, full co-op control.
          </p>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
              <div style={{ width: '30px', height: '30px', borderRadius: '9px', backgroundColor: 'rgba(208, 247, 100, 0.12)', border: '1px solid rgba(208, 247, 100, 0.25)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.75rem', fontWeight: 800, color: 'var(--brand-lime)', flexShrink: 0 }}>
                1
              </div>
              <div>
                <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'rgba(255,255,255,0.9)' }}>Personal Details</div>
                <div style={{ fontSize: '0.72rem', color: 'rgba(255,255,255,0.45)' }}>ID, name, birth date & contact</div>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
              <div style={{ width: '30px', height: '30px', borderRadius: '9px', backgroundColor: 'rgba(208, 247, 100, 0.12)', border: '1px solid rgba(208, 247, 100, 0.25)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.75rem', fontWeight: 800, color: 'var(--brand-lime)', flexShrink: 0 }}>
                2
              </div>
              <div>
                <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'rgba(255,255,255,0.9)' }}>Account Security</div>
                <div style={{ fontSize: '0.72rem', color: 'rgba(255,255,255,0.45)' }}>Email, password & next of kin</div>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
              <div style={{ width: '30px', height: '30px', borderRadius: '9px', backgroundColor: 'rgba(208, 247, 100, 0.12)', border: '1px solid rgba(208, 247, 100, 0.25)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.75rem', fontWeight: 800, color: 'var(--brand-lime)', flexShrink: 0 }}>
                3
              </div>
              <div>
                <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'rgba(255,255,255,0.9)' }}>Portal Access</div>
                <div style={{ fontSize: '0.72rem', color: 'rgba(255,255,255,0.45)' }}>Instant activation & digital pass</div>
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div style={{ fontSize: '0.7rem', color: 'rgba(255,255,255,0.4)' }}>
          &copy; {new Date().getFullYear()} Umoja Drivers Sacco Ltd.
        </div>
      </div>

      {/* ── Right Form Area ── */}
      <div
        className="register-form-side"
        style={{
          flex: 1,
          backgroundColor: 'var(--bg-surface)',
          overflowY: 'auto',
          padding: '48px 52px',
          position: 'relative',
          transition: 'background-color 0.3s ease',
        }}
      >
        <div style={{ maxWidth: '680px', margin: '0 auto' }}>
          {/* Header */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '32px' }}>
            <div>
              <h2 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.5px' }}>
                Create Account
              </h2>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '2px' }}>
                Fill in your details to register as an active member
              </p>
            </div>
            <Link
              href="/login"
              style={{
                fontSize: '0.78rem',
                fontWeight: 800,
                color: 'var(--brand-forest)',
                textTransform: 'uppercase',
                letterSpacing: '0.7px',
                borderBottom: '2px solid var(--brand-lime)',
                paddingBottom: '2px',
              }}
            >
              Sign In Instead
            </Link>
          </div>

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
                marginBottom: '24px',
              }}
            >
              <AlertCircle size={18} />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
            {/* 1. Personal Info */}
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '16px' }}>
                <div style={{ width: '32px', height: '32px', borderRadius: '10px', backgroundColor: '#E8F5E9', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <User size={16} />
                </div>
                <span style={{ fontSize: '0.82rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.8px', color: 'var(--brand-forest)' }}>
                  Personal Information
                </span>
                <div style={{ flex: 1, height: '1px', backgroundColor: 'var(--border-color)' }} />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
                <div style={{ gridColumn: '1 / -1' }}>
                  <label className="input-label">Full Name <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="text"
                    name="full_name"
                    className="input-control"
                    placeholder="As per National ID"
                    value={formData.full_name}
                    onChange={handleChange}
                    required
                  />
                </div>

                <div>
                  <label className="input-label">National ID <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="text"
                    name="national_id"
                    className="input-control"
                    placeholder="8-digit ID number"
                    value={formData.national_id}
                    onChange={handleChange}
                    required
                  />
                </div>

                <div>
                  <label className="input-label">Phone Number <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="tel"
                    name="phone"
                    className="input-control"
                    placeholder="07xxxxxxxx"
                    value={formData.phone}
                    onChange={handleChange}
                    required
                  />
                </div>

                <div>
                  <label className="input-label">Gender</label>
                  <select name="gender" className="form-select" value={formData.gender} onChange={handleChange}>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                  </select>
                </div>

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
                </div>

                <div>
                  <label className="input-label">Occupation</label>
                  <input
                    type="text"
                    name="occupation"
                    className="input-control"
                    placeholder="e.g. Commercial Driver"
                    value={formData.occupation}
                    onChange={handleChange}
                  />
                </div>

                <div>
                  <label className="input-label">Home Address <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="text"
                    name="address"
                    className="input-control"
                    placeholder="e.g. Ruiru, Kiambu"
                    value={formData.address}
                    onChange={handleChange}
                    required
                  />
                </div>
              </div>
            </div>

            {/* 2. Next of Kin */}
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '16px' }}>
                <div style={{ width: '32px', height: '32px', borderRadius: '10px', backgroundColor: '#FFF7ED', color: '#c2410c', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Heart size={16} />
                </div>
                <span style={{ fontSize: '0.82rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.8px', color: 'var(--brand-forest)' }}>
                  Next of Kin Details
                </span>
                <div style={{ flex: 1, height: '1px', backgroundColor: 'var(--border-color)' }} />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
                <div>
                  <label className="input-label">Next of Kin Name <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="text"
                    name="nok_name"
                    className="input-control"
                    placeholder="Full name of beneficiary"
                    value={formData.nok_name}
                    onChange={handleChange}
                    required
                  />
                </div>

                <div>
                  <label className="input-label">Next of Kin Phone <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="tel"
                    name="nok_phone"
                    className="input-control"
                    placeholder="07xxxxxxxx"
                    value={formData.nok_phone}
                    onChange={handleChange}
                    required
                  />
                </div>
              </div>
            </div>

            {/* 3. Account Credentials */}
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '16px' }}>
                <div style={{ width: '32px', height: '32px', borderRadius: '10px', backgroundColor: '#F5F3FF', color: '#7c3aed', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Lock size={16} />
                </div>
                <span style={{ fontSize: '0.82rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.8px', color: 'var(--brand-forest)' }}>
                  Account Credentials
                </span>
                <div style={{ flex: 1, height: '1px', backgroundColor: 'var(--border-color)' }} />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
                <div style={{ gridColumn: 'span 2' }}>
                  <label className="input-label">Email Address <span style={{ color: '#dc2626' }}>*</span></label>
                  <input
                    type="email"
                    name="email"
                    className="input-control"
                    placeholder="name@email.com"
                    value={formData.email}
                    onChange={handleChange}
                    required
                  />
                </div>

                <div>
                  <label className="input-label">Password <span style={{ color: '#dc2626' }}>*</span></label>
                  <div style={{ position: 'relative' }}>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      name="password"
                      className="input-control"
                      placeholder="Min. 6 characters"
                      value={formData.password}
                      onChange={handleChange}
                      style={{ paddingRight: '42px' }}
                      required
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

                <div>
                  <label className="input-label">Confirm Password <span style={{ color: '#dc2626' }}>*</span></label>
                  <div style={{ position: 'relative' }}>
                    <input
                      type={showConfirm ? 'text' : 'password'}
                      name="confirm_password"
                      className="input-control"
                      placeholder="Repeat password"
                      value={formData.confirm_password}
                      onChange={handleChange}
                      style={{ paddingRight: '42px' }}
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirm(!showConfirm)}
                      style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
                    >
                      {showConfirm ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Submit Button */}
            <div>
              <button
                type="submit"
                disabled={loading}
                className="btn btn-forest btn-lg"
                style={{ width: '100%', padding: '16px' }}
              >
                {loading ? 'Creating Your Sacco Account...' : 'Complete Registration & Join Sacco'}
                {!loading && <ArrowRight size={18} />}
              </button>

              <p style={{ textAlign: 'center', fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '16px' }}>
                By creating an account, you agree to our <Link href="/terms" style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>Terms</Link> and <Link href="/privacy" style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>Privacy Policy</Link>.
              </p>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
