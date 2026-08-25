'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { useTheme } from '@/context/ThemeContext';
import {
  Menu,
  X,
  Sun,
  Moon,
  ArrowRight,
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
            href={user.user_type === 'member' ? '/member' : '/admin'}
            className="btn btn-lime"
          >
            <LayoutDashboard size={16} /> Portal Dashboard
          </Link>
        ) : (
          <div className="desktop-links" style={{ display: 'flex', gap: '10px' }}>
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
          </div>
        )}

        {/* Mobile Hamburger Toggle Button */}
        <button
          onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          title="Toggle Navigation Menu"
          style={{
            background: 'rgba(255, 255, 255, 0.1)',
            border: 'none',
            borderRadius: '10px',
            width: '40px',
            height: '40px',
            color: '#FFFFFF',
            display: 'none',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
          }}
          className="mobile-toggle"
        >
          {mobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      </div>

      {/* Mobile Drawer */}
      {mobileMenuOpen && (
        <div
          onClick={() => setMobileMenuOpen(false)}
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.65)',
            backdropFilter: 'blur(4px)',
            zIndex: 998,
          }}
        />
      )}

      <div
        style={{
          position: 'fixed',
          top: '80px',
          left: 0,
          right: 0,
          backgroundColor: '#0F392B',
          borderBottom: '2px solid var(--brand-lime)',
          padding: '24px',
          display: 'flex',
          flexDirection: 'column',
          gap: '16px',
          zIndex: 999,
          transform: mobileMenuOpen ? 'translateY(0)' : 'translateY(-120%)',
          opacity: mobileMenuOpen ? 1 : 0,
          pointerEvents: mobileMenuOpen ? 'auto' : 'none',
          transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
          boxShadow: '0 20px 40px rgba(0,0,0,0.5)',
          maxHeight: 'calc(100vh - 80px)',
          overflowY: 'auto',
        }}
        className="mobile-nav-menu"
      >
        <Link
          href="/#wealth-model"
          onClick={() => setMobileMenuOpen(false)}
          style={{ fontSize: '1rem', fontWeight: 600, color: '#FFFFFF', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.08)' }}
        >
          How It Works
        </Link>
        <Link
          href="/#services"
          onClick={() => setMobileMenuOpen(false)}
          style={{ fontSize: '1rem', fontWeight: 600, color: '#FFFFFF', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.08)' }}
        >
          Services
        </Link>
        <Link
          href="/#calculator"
          onClick={() => setMobileMenuOpen(false)}
          style={{ fontSize: '1rem', fontWeight: 600, color: '#FFFFFF', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.08)' }}
        >
          Loan Calculator
        </Link>
        <Link
          href="/#portfolio"
          onClick={() => setMobileMenuOpen(false)}
          style={{ fontSize: '1rem', fontWeight: 600, color: '#FFFFFF', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.08)' }}
        >
          Investments
        </Link>
        <Link
          href="/faqs"
          onClick={() => setMobileMenuOpen(false)}
          style={{ fontSize: '1rem', fontWeight: 600, color: '#FFFFFF', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.08)' }}
        >
          FAQs
        </Link>
        <Link
          href="/contact"
          onClick={() => setMobileMenuOpen(false)}
          style={{ fontSize: '1rem', fontWeight: 600, color: '#FFFFFF', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.08)' }}
        >
          Contact Support Desk
        </Link>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '10px' }}>
          {user ? (
            <Link
              href={user.user_type === 'member' ? '/member' : '/admin'}
              onClick={() => setMobileMenuOpen(false)}
              className="btn btn-lime"
              style={{ width: '100%', justifyContent: 'center' }}
            >
              <LayoutDashboard size={18} /> Go to Dashboard
            </Link>
          ) : (
            <>
              <Link
                href="/login"
                onClick={() => setMobileMenuOpen(false)}
                className="btn btn-outline-lime"
                style={{ width: '100%', justifyContent: 'center', color: '#FFFFFF', borderColor: 'var(--brand-lime)' }}
              >
                Sign In
              </Link>
              <Link
                href="/register"
                onClick={() => setMobileMenuOpen(false)}
                className="btn btn-lime"
                style={{ width: '100%', justifyContent: 'center' }}
              >
                Join Sacco <ArrowRight size={18} />
              </Link>
            </>
          )}
        </div>
      </div>
    </nav>
  );
}
