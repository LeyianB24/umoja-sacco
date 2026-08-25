'use client';

import React from 'react';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';

export default function PrivacyPage() {
  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <Navbar />
      <main style={{ flex: 1, maxWidth: '880px', margin: '0 auto', padding: '60px 24px 80px' }}>
        <h1 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '14px' }}>
          Privacy Policy & Data Protection
        </h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.92rem', marginBottom: '36px' }}>
          In compliance with the Kenya Data Protection Act, 2019
        </p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', color: 'var(--text-main)', lineHeight: 1.7, fontSize: '0.98rem' }}>
          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              1. Information We Collect
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              We collect personal identification data (Full Name, National ID, Phone Number, Date of Birth, Next of Kin, Email, and KYC identification documents) strictly for membership verification, regulatory SASRA reporting, and secure financial transactions.
            </p>
          </section>

          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              2. Data Protection & Security
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              All financial records, identity documents, and sensitive credentials are encrypted using industry-standard TLS protocols and bcrypt password hashing. Access is strictly controlled via Role-Based Access Control (RBAC).
            </p>
          </section>

          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              3. Third-Party Sharing
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              We do not sell or rent member data. Data is only shared with licensed payment gateways (Safaricom M-Pesa) and regulatory statutory bodies (SASRA, KRA) as mandated by law.
            </p>
          </section>
        </div>
      </main>
      <Footer />
    </div>
  );
}
