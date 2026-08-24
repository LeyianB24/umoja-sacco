'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { User, Lock, Eye, EyeOff, ShieldCheck, ArrowRight, AlertCircle } from 'lucide-react';

export default function LoginPage() {
  const router = useRouter();
  const { login } = useAuth();
  const { toast } = useToast();

  const [tab, setTab] = useState<'member' | 'admin'>('member');
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!identifier || !password) {
      setError('Please enter both your email/username and password.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const res = await login({
        identifier,
        password,
        user_type: tab,
      });

      toast.success(`Welcome back, ${res.user?.name || 'User'}!`);
      const redirectUrl = res.redirect_to || (tab === 'admin' ? '/admin' : '/member');
      router.push(redirectUrl);
    } catch (err: any) {
      setError(err.message || 'Invalid login credentials. Please verify your details.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: `linear-gradient(rgba(11, 30, 22, 0.85), rgba(15, 57, 43, 0.88)), url('/assets/images/sacco3.jpg')`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        backgroundAttachment: 'fixed',
        padding: '24px',
        position: 'relative',
      }}
    >
      <div
        style={{
          width: '100%',
          maxWidth: '1000px',
          backgroundColor: 'var(--bg-surface)',
          borderRadius: '24px',
          overflow: 'hidden',
          display: 'flex',
          boxShadow: '0 25px 60px rgba(0, 0, 0, 0.4)',
          border: '1px solid rgba(255, 255, 255, 0.15)',
        }}
        className="login-container-responsive"
      >
        {/* ── Left Branding Panel ── */}
        <div
          style={{
            flex: 1,
            background: `linear-gradient(135deg, rgba(15, 57, 43, 0.94) 0%, rgba(26, 92, 67, 0.92) 100%), url('/assets/images/sacco3.jpg')`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            padding: '50px 44px',
            color: '#FFFFFF',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'space-between',
            position: 'relative',
            overflow: 'hidden',
          }}
          className="login-brand-side"
        >
          {/* Ambient Glow */}
          <div
            style={{
              position: 'absolute',
              top: '-100px',
              right: '-100px',
              width: '300px',
              height: '300px',
              background: 'radial-gradient(circle, rgba(208, 247, 100, 0.18) 0%, transparent 70%)',
              borderRadius: '50%',
              pointerEvents: 'none',
            }}
          />

          <div style={{ position: 'relative', zIndex: 2 }}>
            <Link href="/" style={{ display: 'inline-flex', alignItems: 'center', gap: '12px', marginBottom: '40px' }}>
              <div
                style={{
                  width: '46px',
                  height: '46px',
                  borderRadius: '12px',
                  backgroundColor: '#FFFFFF',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  overflow: 'hidden',
                  padding: '3px',
                  boxShadow: '0 4px 14px rgba(0,0,0,0.25)',
                }}
              >
                <img
                  src="/assets/images/people_logo.png"
                  alt="Logo"
                  style={{ width: '100%', height: '100%', objectFit: 'contain' }}
                />
              </div>
              <span style={{ fontWeight: 800, fontSize: '1.25rem', color: '#FFFFFF', letterSpacing: '-0.3px' }}>
                UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
              </span>
            </Link>

            <h2 style={{ fontSize: '2.4rem', fontWeight: 800, lineHeight: 1.15, marginBottom: '16px' }}>
              Secure <br />
              Access to your <br />
              <span style={{ color: 'var(--brand-lime)' }}>Wealth.</span>
            </h2>

            <p style={{ color: 'rgba(255, 255, 255, 0.8)', fontSize: '0.98rem', lineHeight: 1.6, maxWidth: '360px' }}>
              Enter your credentials to manage your savings, shares, and loans in the ultimate Sacco ecosystem.
            </p>
          </div>

          <div style={{ display: 'flex', gap: '28px', borderTop: '1px solid rgba(255, 255, 255, 0.18)', paddingTop: '24px', marginTop: '40px', position: 'relative', zIndex: 2 }}>
            <div>
              <span style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--brand-lime)', display: 'block', lineHeight: 1 }}>100%</span>
              <span style={{ fontSize: '0.7rem', textTransform: 'uppercase', letterSpacing: '1px', color: 'rgba(255, 255, 255, 0.7)' }}>Secure</span>
            </div>
            <div>
              <span style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--brand-lime)', display: 'block', lineHeight: 1 }}>24/7</span>
              <span style={{ fontSize: '0.7rem', textTransform: 'uppercase', letterSpacing: '1px', color: 'rgba(255, 255, 255, 0.7)' }}>Access</span>
            </div>
            <div>
              <span style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--brand-lime)', display: 'block', lineHeight: 1 }}>ACID</span>
              <span style={{ fontSize: '0.7rem', textTransform: 'uppercase', letterSpacing: '1px', color: 'rgba(255, 255, 255, 0.7)' }}>Ledger</span>
            </div>
          </div>
        </div>

        {/* ── Right Form Panel ── */}
        <div
          style={{
            flex: 1,
            padding: '50px 48px',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'center',
            backgroundColor: 'var(--bg-surface)',
          }}
        >
          <div style={{ marginBottom: '24px' }}>
            <h3 style={{ fontSize: '1.65rem', fontWeight: 800, color: 'var(--brand-forest)', letterSpacing: '-0.5px' }}>
              Welcome back
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginTop: '2px' }}>
              Please enter your account credentials
            </p>
          </div>

          {/* Dual Tab Switcher */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: '1fr 1fr',
              backgroundColor: 'var(--surface-2)',
              padding: '5px',
              borderRadius: '50px',
              border: '1px solid var(--border-color)',
              marginBottom: '24px',
            }}
          >
            <button
              type="button"
              onClick={() => {
                setTab('member');
                setError('');
              }}
              style={{
                padding: '8px',
                borderRadius: '50px',
                border: 'none',
                fontWeight: 700,
                fontSize: '0.85rem',
                cursor: 'pointer',
                transition: 'all 0.2s',
                backgroundColor: tab === 'member' ? 'var(--brand-forest)' : 'transparent',
                color: tab === 'member' ? '#FFFFFF' : 'var(--text-muted)',
              }}
            >
              Member Portal
            </button>
            <button
              type="button"
              onClick={() => {
                setTab('admin');
                setError('');
              }}
              style={{
                padding: '8px',
                borderRadius: '50px',
                border: 'none',
                fontWeight: 700,
                fontSize: '0.85rem',
                cursor: 'pointer',
                transition: 'all 0.2s',
                backgroundColor: tab === 'admin' ? 'var(--brand-forest)' : 'transparent',
                color: tab === 'admin' ? '#FFFFFF' : 'var(--text-muted)',
              }}
            >
              Staff / Admin
            </button>
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
                marginBottom: '20px',
              }}
            >
              <AlertCircle size={18} style={{ flexShrink: 0 }} />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
            <div>
              <label className="input-label">
                {tab === 'member' ? 'Email or Member Reg No.' : 'Staff Username or Email'}
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  type="text"
                  className="input-control"
                  placeholder={tab === 'member' ? 'name@example.com or USMS-2026-0001' : 'admin or staff@umojasacco.co.ke'}
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  required
                />
              </div>
            </div>

            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '7px' }}>
                <label className="input-label" style={{ margin: 0 }}>Password</label>
                <Link href="/forgot-password" style={{ fontSize: '0.82rem', color: 'var(--brand-forest)', fontWeight: 700 }}>
                  Forgot?
                </Link>
              </div>
              <div style={{ position: 'relative' }}>
                <input
                  type={showPassword ? 'text' : 'password'}
                  className="input-control"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  style={{ paddingRight: '44px' }}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  style={{
                    position: 'absolute',
                    right: '14px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    color: 'var(--text-muted)',
                  }}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <input
                type="checkbox"
                id="remember"
                checked={remember}
                onChange={(e) => setRemember(e.target.checked)}
                style={{ cursor: 'pointer', accentColor: '#0F392B' }}
              />
              <label htmlFor="remember" style={{ fontSize: '0.85rem', color: 'var(--text-muted)', cursor: 'pointer' }}>
                Remember this device
              </label>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="btn btn-forest btn-lg"
              style={{ width: '100%', marginTop: '6px' }}
            >
              {loading ? 'Authenticating...' : 'Sign In to Dashboard'}
              {!loading && <ArrowRight size={18} />}
            </button>
          </form>

          {tab === 'member' && (
            <div style={{ textAlign: 'center', marginTop: '24px', fontSize: '0.88rem', color: 'var(--text-muted)' }}>
              Don't have an account?{' '}
              <Link href="/register" style={{ color: 'var(--brand-forest)', fontWeight: 700 }}>
                Join Umoja Drivers Sacco
              </Link>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
