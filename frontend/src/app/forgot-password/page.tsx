'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { ArrowLeft, Mail, CheckCircle2, AlertCircle } from 'lucide-react';

export default function ForgotPasswordPage() {
  const { toast } = useToast();
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email) return;

    setLoading(true);
    setError('');

    try {
      await api.post('/auth/forgot_password', { email });
      setSubmitted(true);
      toast.success('Password reset instructions dispatched.');
    } catch (err: any) {
      setError(err.message || 'Failed to request password reset.');
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
      }}
    >
      <div
        style={{
          width: '100%',
          maxWidth: '460px',
          backgroundColor: 'var(--bg-surface)',
          borderRadius: '24px',
          border: '1px solid var(--border-color)',
          boxShadow: '0 25px 50px rgba(0, 0, 0, 0.35)',
          overflow: 'hidden',
        }}
      >
        {/* Header */}
        <div
          style={{
            background: 'linear-gradient(135deg, #0B1E17 0%, #0F392B 100%)',
            color: '#FFFFFF',
            padding: '32px 32px 24px',
            textAlign: 'center',
            borderBottom: '1px solid rgba(208, 247, 100, 0.15)',
          }}
        >
          <Link href="/" style={{ display: 'inline-flex', alignItems: 'center', gap: '12px', marginBottom: '14px' }}>
            <div
              style={{
                width: '42px',
                height: '42px',
                borderRadius: '12px',
                backgroundColor: '#FFFFFF',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
                padding: '2px',
              }}
            >
              <img
                src="/assets/images/people_logo.png"
                alt="Umoja Sacco"
                style={{ width: '100%', height: '100%', objectFit: 'contain' }}
              />
            </div>
            <span style={{ fontWeight: 800, fontSize: '1.25rem', color: '#FFFFFF' }}>
              UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
            </span>
          </Link>
          <h2 style={{ fontSize: '1.35rem', fontWeight: 800 }}>
            Account Password Reset
          </h2>
          <p style={{ color: 'rgba(255, 255, 255, 0.65)', fontSize: '0.85rem', marginTop: '4px' }}>
            We'll send recovery instructions to your email
          </p>
        </div>

        <div style={{ padding: '32px' }}>
          {submitted ? (
            <div style={{ textAlign: 'center', padding: '16px 0' }}>
              <div style={{ width: '56px', height: '56px', borderRadius: '50%', backgroundColor: 'rgba(34, 197, 94, 0.1)', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 16px' }}>
                <CheckCircle2 size={32} />
              </div>
              <h3 style={{ fontSize: '1.15rem', fontWeight: 800, color: 'var(--brand-forest)', marginBottom: '8px' }}>
                Check Your Email
              </h3>
              <p style={{ fontSize: '0.88rem', color: 'var(--text-muted)', marginBottom: '24px', lineHeight: 1.6 }}>
                If <strong style={{ color: 'var(--text-main)' }}>{email}</strong> is registered in our records, a secure password reset link has been dispatched.
              </p>
              <Link href="/login" className="btn btn-lime btn-lg" style={{ width: '100%' }}>
                Return to Login
              </Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
              {error && (
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    padding: '12px 16px',
                    borderRadius: '12px',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    color: '#dc2626',
                    fontSize: '0.85rem',
                    fontWeight: 600,
                  }}
                >
                  <AlertCircle size={18} />
                  <span>{error}</span>
                </div>
              )}

              <div>
                <label className="input-label">Registered Account Email</label>
                <input
                  type="email"
                  className="input-control"
                  placeholder="e.g. member@email.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoFocus
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="btn btn-forest btn-lg"
                style={{ width: '100%' }}
              >
                {loading ? 'Sending Request...' : 'Send Recovery Instructions'}
              </button>

              <div style={{ textAlign: 'center', marginTop: '6px' }}>
                <Link
                  href="/login"
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '6px',
                    fontSize: '0.85rem',
                    color: 'var(--brand-forest)',
                    fontWeight: 700,
                  }}
                >
                  <ArrowLeft size={16} /> Back to Sign In
                </Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
