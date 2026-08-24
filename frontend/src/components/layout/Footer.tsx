'use client';

import React from 'react';
import Link from 'next/link';
import {
  Phone,
  Mail,
  MapPin,
  ShieldCheck,
  ArrowUpRight,
  Heart,
} from 'lucide-react';

export function Footer() {
  return (
    <footer
      style={{
        backgroundColor: '#07140F',
        color: 'rgba(255, 255, 255, 0.75)',
        borderTop: '1px solid rgba(208, 247, 100, 0.15)',
        padding: '70px 24px 30px',
        fontSize: '0.9rem',
      }}
    >
      <div
        style={{
          maxWidth: '1240px',
          margin: '0 auto',
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
          gap: '40px',
          marginBottom: '60px',
        }}
      >
        {/* Brand Col */}
        <div>
          <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '18px' }}>
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
                padding: '2px',
              }}
            >
              <img
                src="/assets/images/people_logo.png"
                alt="Umoja Sacco"
                style={{ width: '100%', height: '100%', objectFit: 'contain' }}
              />
            </div>
            <span style={{ fontSize: '1.2rem', fontWeight: 800, color: '#FFFFFF', letterSpacing: '-0.5px' }}>
              UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
            </span>
          </Link>
          <p style={{ lineHeight: 1.6, fontSize: '0.88rem', color: 'rgba(255, 255, 255, 0.65)', marginBottom: '20px' }}>
            Empowering drivers, fleet operators, and transport professionals across Kenya with high-yield savings, low-interest credit, and solid welfare protection.
          </p>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--brand-lime)', fontSize: '0.8rem', fontWeight: 700 }}>
            <ShieldCheck size={16} /> SASRA Regulated Cooperative
          </div>
        </div>

        {/* Quick Links */}
        <div>
          <h4 style={{ color: '#FFFFFF', fontWeight: 700, fontSize: '0.95rem', marginBottom: '18px' }}>
            Quick Links
          </h4>
          <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <li>
              <Link href="/#wealth-model" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                How It Works
              </Link>
            </li>
            <li>
              <Link href="/#calculator" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Loan Calculator
              </Link>
            </li>
            <li>
              <Link href="/#portfolio" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Asset Investments
              </Link>
            </li>
            <li>
              <Link href="/faqs" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Help & FAQs
              </Link>
            </li>
            <li>
              <Link href="/login" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Member Sign In
              </Link>
            </li>
          </ul>
        </div>

        {/* Legal */}
        <div>
          <h4 style={{ color: '#FFFFFF', fontWeight: 700, fontSize: '0.95rem', marginBottom: '18px' }}>
            Governance & Legal
          </h4>
          <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '10px' }}>
            <li>
              <Link href="/terms" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Terms & By-Laws
              </Link>
            </li>
            <li>
              <Link href="/privacy" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Privacy & Data Protection
              </Link>
            </li>
            <li>
              <Link href="/contact" style={{ transition: 'color 0.2s', color: 'rgba(255,255,255,0.7)' }}>
                Customer Care Desk
              </Link>
            </li>
          </ul>
        </div>

        {/* Contact Info */}
        <div>
          <h4 style={{ color: '#FFFFFF', fontWeight: 700, fontSize: '0.95rem', marginBottom: '18px' }}>
            Head Office
          </h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', fontSize: '0.88rem' }}>
            <div style={{ display: 'flex', gap: '10px', alignItems: 'flex-start' }}>
              <MapPin size={18} style={{ color: 'var(--brand-lime)', flexShrink: 0, marginTop: '2px' }} />
              <span>Umoja Sacco Plaza, Commercial Avenue, Nairobi, Kenya</span>
            </div>
            <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
              <Phone size={18} style={{ color: 'var(--brand-lime)', flexShrink: 0 }} />
              <span>+254 700 000 000 / Toll Free 0800 000</span>
            </div>
            <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
              <Mail size={18} style={{ color: 'var(--brand-lime)', flexShrink: 0 }} />
              <span>info@umojasacco.co.ke</span>
            </div>
          </div>
        </div>
      </div>

      {/* Bottom bar */}
      <div
        style={{
          maxWidth: '1240px',
          margin: '0 auto',
          paddingTop: '24px',
          borderTop: '1px solid rgba(255, 255, 255, 0.1)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '14px',
          fontSize: '0.8rem',
          color: 'rgba(255, 255, 255, 0.5)',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
          <span>&copy; {new Date().getFullYear()} Umoja Drivers & Allied Sacco Society Ltd. All rights reserved.</span>
          <span>&bull;</span>
          <span>Built by <a href="https://www.bezalel.website/" target="_blank" rel="noopener noreferrer" style={{ color: 'var(--brand-lime)', textDecoration: 'none', fontWeight: 600 }}>Bezalel Technologies</a></span>
        </div>
        <div style={{ display: 'flex', gap: '16px' }}>
          <Link href="/terms">Terms</Link>
          <Link href="/privacy">Privacy</Link>
          <Link href="/contact">Support</Link>
        </div>
      </div>
    </footer>
  );
}
