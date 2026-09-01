'use client';

import React from 'react';
import Link from 'next/link';
import { Home, ArrowLeft, ShieldAlert } from 'lucide-react';

export default function NotFound() {
  return (
    <div
      style={{
        minHeight: '80vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '24px',
        textAlign: 'center',
      }}
    >
      <div
        style={{
          width: '80px',
          height: '80px',
          borderRadius: '50%',
          backgroundColor: 'rgba(11, 36, 25, 0.08)',
          color: 'var(--brand-forest)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          marginBottom: '24px',
        }}
      >
        <ShieldAlert size={40} />
      </div>

      <h1 style={{ fontSize: '3rem', fontWeight: 900, color: 'var(--brand-forest)', margin: 0, letterSpacing: '-1px' }}>
        404
      </h1>
      <h2 style={{ fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-main)', margin: '8px 0 12px' }}>
        Page or Resource Not Found
      </h2>
      <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem', maxWidth: '460px', marginBottom: '28px', lineHeight: 1.5 }}>
        The page you are looking for might have been moved, deleted, or does not exist in the Umoja SACCO portal.
      </p>

      <div style={{ display: 'flex', gap: '12px' }}>
        <Link href="/" className="btn btn-forest" style={{ textDecoration: 'none' }}>
          <Home size={16} /> Return to Homepage
        </Link>
        <Link href="/member" className="btn btn-outline-forest" style={{ textDecoration: 'none' }}>
          <ArrowLeft size={16} /> Member Portal
        </Link>
      </div>
    </div>
  );
}
