'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useAuth } from '@/context/AuthContext';
import { useTheme } from '@/context/ThemeContext';
import {
  Menu,
  X,
  Sun,
  Moon,
  Shield,
  ArrowRight,
  User,
  LayoutDashboard,
} from 'lucide-react';

export function Navbar() {
  const { user } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <nav
      style={{
        position: 'sticky',
        top: 0,
        zIndex: 100,
        backgroundColor: 'rgba(15, 57, 43, 0.95)',
        backdropFilter: 'blur(12px)',
        borderBottom: '1px solid rgba(208, 247, 100, 0.15)',
        padding: '0 24px',
        height: '80px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        color: '#FFFFFF',
      }}
    >
      {/* Brand / Logo */}
      <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
        <div
          style={{
            width: '46px',
            height: '46px',
            borderRadius: '14px',
            backgroundColor: '#FFFFFF',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            overflow: 'hidden',
            boxShadow: '0 4px 14px rgba(0,0,0,0.2)',
            padding: '2px',
          }}
        >
          <img
            src="/assets/images/people_logo.png"
            alt="Umoja Sacco Logo"
            style={{ width: '100%', height: '100%', objectFit: 'contain' }}
          />
        </div>
        <div>
          <span
            style={{
              fontSize: '1.25rem',
              fontWeight: 800,
              letterSpacing: '-0.5px',
              display: 'block',
              lineHeight: 1.1,
            }}
          >
            UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
          </span>
          <small
            style={{
              fontSize: '0.65rem',
              letterSpacing: '1px',
              textTransform: 'uppercase',
              color: 'rgba(255, 255, 255, 0.7)',
              fontWeight: 600,
            }}
          >
            Savings & Credit Society
          </small>
        </div>
      </Link>

      {/* Desktop Links */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '32px',
        }}
        className="desktop-links"
      >
        <Link
          href="/#wealth-model"
          style={{ fontSize: '0.92rem', fontWeight: 600, color: 'rgba(255, 255, 255, 0.85)', transition: 'color 0.2s' }}
        >
          How It Works
        </Link>
        <Link
          href="/#services"
          style={{ fontSize: '0.92rem', fontWeight: 600, color: 'rgba(255, 255, 255, 0.85)', transition: 'color 0.2s' }}
        >
          Services
        </Link>
        <Link
          href="/#calculator"
          style={{ fontSize: '0.92rem', fontWeight: 600, color: 'rgba(255, 255, 255, 0.85)', transition: 'color 0.2s' }}
        >
          Loan Calculator
        </Link>
        <Link
          href="/#portfolio"
          style={{ fontSize: '0.92rem', fontWeight: 600, color: 'rgba(255, 255, 255, 0.85)', transition: 'color 0.2s' }}
        >
          Investments
        </Link>
        <Link
          href="/faqs"
          style={{ fontSize: '0.92rem', fontWeight: 600, color: 'rgba(255, 255, 255, 0.85)', transition: 'color 0.2s' }}
        >
          FAQs
        </Link>
        <Link
          href="/contact"
          style={{ fontSize: '0.92rem', fontWeight: 600, color: 'rgba(255, 255, 255, 0.85)', transition: 'color 0.2s' }}
        >
          Contact
        </Link>
      </div>

      {/* Actions */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
        <button
          onClick={toggleTheme}
          title="Toggle Theme"
          style={{
            background: 'rgba(255, 255, 255, 0.1)',
            border: 'none',
            borderRadius: '50%',
            width: '40px',
            height: '40px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: '#FFFFFF',
            cursor: 'pointer',
          }}
        >
          {theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
        </button>

        {user ? (
          <Link
            href={user.role === 'member' ? '/member' : '/admin'}
            className="btn btn-lime"
          >
            <LayoutDashboard size={16} /> Portal Dashboard
          </Link>
        ) : (
          <>
            <Link
              href="/login"
              className="btn btn-outline-lime"
              style={{ padding: '8px 20px', fontSize: '0.88rem' }}
            >
              Sign In
            </Link>
            <Link
              href="/register"
              className="btn btn-lime"
              style={{ padding: '8px 22px', fontSize: '0.88rem' }}
            >
              Join Sacco <ArrowRight size={16} />
            </Link>
          </>
        )}

        {/* Mobile Toggle */}
        <button
          onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          style={{
            background: 'none',
            border: 'none',
            color: '#FFFFFF',
            display: 'none',
            cursor: 'pointer',
          }}
          className="mobile-toggle"
        >
          {mobileMenuOpen ? <X size={26} /> : <Menu size={26} />}
        </button>
      </div>
    </nav>
  );
}
