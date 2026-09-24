'use client';

import React, { useState, useEffect, useRef } from 'react';
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
  const [scrolled, setScrolled] = useState(false);
  const navRef = useRef<HTMLElement>(null);

  // Track window scroll to dynamically elevate floating navbar
  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Close mobile drawer on Escape key or outside click
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && mobileMenuOpen) {
        setMobileMenuOpen(false);
      }
    };

    const handleClickOutside = (e: MouseEvent) => {
      if (navRef.current && !navRef.current.contains(e.target as Node)) {
        setMobileMenuOpen(false);
      }
    };

    if (mobileMenuOpen) {
      document.addEventListener('keydown', handleKeyDown);
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [mobileMenuOpen]);

  const navLinks = [
    { label: 'How It Works', href: '/#wealth-model' },
    { label: 'Services', href: '/#services' },
    { label: 'Loan Calculator', href: '/#calculator' },
    { label: 'Investments', href: '/#portfolio' },
    { label: 'FAQs', href: '/faqs' },
    { label: 'Contact', href: '/contact' },
  ];

  const isDark = theme === 'dark';

  return (
    <header
      className="floating-nav-wrapper"
      style={{
        position: 'sticky',
        top: 'clamp(8px, 1.8vw, 18px)',
        zIndex: 1000,
        width: '100%',
        padding: '0 clamp(8px, 2.5vw, 24px)',
        display: 'flex',
        justifyContent: 'center',
        pointerEvents: 'none',
        transition: 'top 0.3s ease',
      }}
    >
      <nav
        ref={navRef}
        className={`floating-navbar ${scrolled ? 'is-scrolled' : ''}`}
        aria-label="Main Navigation"
        style={{
          pointerEvents: 'auto',
          width: '100%',
          maxWidth: '1240px',
          height: scrolled ? 'clamp(58px, 6vw, 68px)' : 'clamp(64px, 6.8vw, 76px)',
          borderRadius: 'clamp(14px, 2vw, 22px)',
          backgroundColor: isDark
            ? (scrolled ? 'rgba(10, 14, 12, 0.88)' : 'rgba(15, 20, 18, 0.78)')
            : (scrolled ? 'rgba(15, 57, 43, 0.94)' : 'rgba(15, 57, 43, 0.86)'),
          backdropFilter: 'blur(22px) saturate(190%)',
          WebkitBackdropFilter: 'blur(22px) saturate(190%)',
          border: isDark
            ? (scrolled ? '1px solid rgba(255, 255, 255, 0.16)' : '1px solid rgba(255, 255, 255, 0.10)')
            : (scrolled ? '1px solid rgba(208, 247, 100, 0.35)' : '1px solid rgba(208, 247, 100, 0.20)'),
          boxShadow: isDark
            ? (scrolled
                ? '0 20px 42px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05)'
                : '0 12px 28px -8px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.03)')
            : (scrolled
                ? '0 20px 44px -12px rgba(1, 47, 19, 0.55), 0 0 0 1px rgba(208, 247, 100, 0.15)'
                : '0 12px 30px -8px rgba(1, 47, 19, 0.35), 0 0 0 1px rgba(208, 247, 100, 0.1)'),
          padding: '0 clamp(10px, 1.8vw, 22px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          color: '#FFFFFF',
          transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
          position: 'relative',
        }}
      >
        {/* Brand / Logo */}
        <Link
          href="/"
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 'clamp(8px, 1.2vw, 12px)',
            minWidth: 0,
            textDecoration: 'none',
          }}
        >
          <div
            className="floating-nav-logo-box"
            style={{
              width: '42px',
              height: '42px',
              borderRadius: '12px',
              backgroundColor: '#FFFFFF',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              overflow: 'hidden',
              boxShadow: '0 4px 14px rgba(0,0,0,0.22)',
              padding: '2px',
              flexShrink: 0,
              transition: 'transform 0.2s ease',
            }}
          >
            <img
              src="/assets/images/people_logo.png"
              alt="Umoja Sacco Logo"
              style={{ width: '100%', height: '100%', objectFit: 'contain' }}
            />
          </div>
          <div style={{ minWidth: 0 }}>
            <span
              className="floating-nav-title"
              style={{
                fontSize: 'clamp(1rem, 1.4vw, 1.22rem)',
                fontWeight: 800,
                letterSpacing: '-0.5px',
                display: 'block',
                lineHeight: 1.1,
                whiteSpace: 'nowrap',
                color: '#FFFFFF',
              }}
            >
              UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
            </span>
            <small
              className="floating-nav-subtitle"
              style={{
                fontSize: '0.62rem',
                letterSpacing: '0.8px',
                textTransform: 'uppercase',
                color: 'rgba(255, 255, 255, 0.75)',
                fontWeight: 600,
                display: 'block',
                whiteSpace: 'nowrap',
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
            gap: 'clamp(16px, 1.8vw, 28px)',
          }}
          className="desktop-links"
        >
          {navLinks.map((link) => (
            <Link
              key={link.label}
              href={link.href}
              style={{
                fontSize: '0.88rem',
                fontWeight: 600,
                color: 'rgba(255, 255, 255, 0.85)',
                transition: 'color 0.2s ease, transform 0.2s ease',
                textDecoration: 'none',
                padding: '6px 8px',
                borderRadius: '8px',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.color = 'var(--brand-lime)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.color = 'rgba(255, 255, 255, 0.85)';
              }}
            >
              {link.label}
            </Link>
          ))}
        </div>

        {/* Actions & Utilities */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 'clamp(8px, 1vw, 12px)', flexShrink: 0 }}>
          <button
            onClick={toggleTheme}
            type="button"
            title="Toggle Light / Dark Mode"
            aria-label="Toggle Theme"
            style={{
              background: 'rgba(255, 255, 255, 0.12)',
              border: '1px solid rgba(255, 255, 255, 0.15)',
              borderRadius: '50%',
              width: '38px',
              height: '38px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: '#FFFFFF',
              cursor: 'pointer',
              transition: 'background-color 0.2s, transform 0.15s',
              flexShrink: 0,
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.background = 'rgba(255, 255, 255, 0.22)';
              e.currentTarget.style.transform = 'scale(1.05)';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.background = 'rgba(255, 255, 255, 0.12)';
              e.currentTarget.style.transform = 'scale(1)';
            }}
          >
            {isDark ? <Sun size={17} color="#d0f764" /> : <Moon size={17} color="#d0f764" />}
          </button>

          {user ? (
            <Link
              href={user.user_type === 'member' ? '/member' : '/admin'}
              className="btn btn-lime"
              style={{
                padding: '8px 16px',
                fontSize: '0.85rem',
                borderRadius: '12px',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
              }}
            >
              <LayoutDashboard size={15} /> Dashboard
            </Link>
          ) : (
            <div className="desktop-links" style={{ display: 'flex', gap: '8px' }}>
              <Link
                href="/login"
                className="btn btn-outline-lime"
                style={{
                  padding: '7px 16px',
                  fontSize: '0.85rem',
                  borderRadius: '12px',
                  borderColor: 'rgba(208, 247, 100, 0.6)',
                  color: '#FFFFFF',
                }}
              >
                Sign In
              </Link>
              <Link
                href="/register"
                className="btn btn-lime"
                style={{
                  padding: '7px 18px',
                  fontSize: '0.85rem',
                  borderRadius: '12px',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                }}
              >
                Join Sacco <ArrowRight size={15} />
              </Link>
            </div>
          )}

          {/* Mobile Hamburger Toggle Button */}
          <button
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            type="button"
            title="Toggle Navigation Menu"
            aria-label="Toggle navigation"
            aria-expanded={mobileMenuOpen}
            style={{
              background: 'rgba(255, 255, 255, 0.12)',
              border: '1px solid rgba(255, 255, 255, 0.15)',
              borderRadius: '10px',
              width: '38px',
              height: '38px',
              color: '#FFFFFF',
              display: 'none',
              alignItems: 'center',
              justifyContent: 'center',
              cursor: 'pointer',
              flexShrink: 0,
            }}
            className="mobile-toggle"
          >
            {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
          </button>
        </div>

        {/* Floating Mobile Dropdown Drawer */}
        {mobileMenuOpen && (
          <div
            className="floating-mobile-drawer"
            style={{
              position: 'absolute',
              top: 'calc(100% + 10px)',
              left: 0,
              right: 0,
              backgroundColor: isDark ? 'rgba(10, 14, 12, 0.96)' : 'rgba(12, 45, 34, 0.96)',
              backdropFilter: 'blur(24px) saturate(190%)',
              WebkitBackdropFilter: 'blur(24px) saturate(190%)',
              border: isDark ? '1px solid rgba(255, 255, 255, 0.15)' : '1px solid rgba(208, 247, 100, 0.3)',
              borderRadius: 'clamp(14px, 2vw, 20px)',
              boxShadow: '0 24px 50px -10px rgba(0, 0, 0, 0.65)',
              padding: 'clamp(16px, 3vw, 22px)',
              display: 'flex',
              flexDirection: 'column',
              gap: '10px',
              zIndex: 1010,
              maxHeight: 'calc(100vh - 120px)',
              overflowY: 'auto',
            }}
          >
            {navLinks.map((link) => (
              <Link
                key={link.label}
                href={link.href}
                onClick={() => setMobileMenuOpen(false)}
                style={{
                  fontSize: '0.95rem',
                  fontWeight: 600,
                  color: '#FFFFFF',
                  padding: '8px 10px',
                  borderRadius: '8px',
                  borderBottom: '1px solid rgba(255,255,255,0.06)',
                  textDecoration: 'none',
                  transition: 'background-color 0.2s',
                }}
              >
                {link.label}
              </Link>
            ))}

            {/* Mobile Theme Toggle Row */}
            <button
              onClick={toggleTheme}
              type="button"
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '10px 14px',
                borderRadius: '12px',
                backgroundColor: 'rgba(255, 255, 255, 0.08)',
                border: '1px solid rgba(255, 255, 255, 0.15)',
                color: '#FFFFFF',
                fontSize: '0.9rem',
                fontWeight: 700,
                cursor: 'pointer',
                marginTop: '4px',
              }}
            >
              <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                {isDark ? <Sun size={17} color="#d0f764" /> : <Moon size={17} color="#d0f764" />}
                {isDark ? 'Light Mode' : 'Dark Mode (Black)'}
              </span>
              <span style={{ fontSize: '0.72rem', color: 'var(--brand-lime)', fontWeight: 800 }}>
                {isDark ? 'ACTIVE' : 'OFF'}
              </span>
            </button>

            {/* Mobile Auth Actions */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginTop: '6px' }}>
              {user ? (
                <Link
                  href={user.user_type === 'member' ? '/member' : '/admin'}
                  onClick={() => setMobileMenuOpen(false)}
                  className="btn btn-lime"
                  style={{ width: '100%', justifyContent: 'center', padding: '10px' }}
                >
                  <LayoutDashboard size={17} /> Go to Dashboard
                </Link>
              ) : (
                <>
                  <Link
                    href="/login"
                    onClick={() => setMobileMenuOpen(false)}
                    className="btn btn-outline-lime"
                    style={{
                      width: '100%',
                      justifyContent: 'center',
                      color: '#FFFFFF',
                      borderColor: 'var(--brand-lime)',
                      padding: '10px',
                    }}
                  >
                    Sign In
                  </Link>
                  <Link
                    href="/register"
                    onClick={() => setMobileMenuOpen(false)}
                    className="btn btn-lime"
                    style={{ width: '100%', justifyContent: 'center', padding: '10px' }}
                  >
                    Join Sacco <ArrowRight size={17} />
                  </Link>
                </>
              )}
            </div>
          </div>
        )}
      </nav>
    </header>
  );
}
