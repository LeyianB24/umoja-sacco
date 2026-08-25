'use client';

import React from 'react';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';

export default function TermsPage() {
  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <Navbar />
      <main style={{ flex: 1, maxWidth: '880px', margin: '0 auto', padding: '60px 24px 80px' }}>
        <h1 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '14px' }}>
          Terms of Service & Sacco By-Laws
        </h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.92rem', marginBottom: '36px' }}>
          Last revised: May 2026 • Governed by the Sacco Societies Act (SASRA)
        </p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', color: 'var(--text-main)', lineHeight: 1.7, fontSize: '0.98rem' }}>
          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              1. Membership Eligibility & Admission
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              Membership into Umoja Drivers & Allied Sacco Society Ltd is open to commercial transport operators, fleet owners, delivery personnel, and allied professionals. An applicant becomes a full member upon complete submission of identification documents, payment of the non-refundable registration fee, and minimum monthly savings contribution.
            </p>
          </section>

          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              2. Savings & Share Capital
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              Members must maintain active monthly savings contributions. Share capital represents the permanent equity of the member in the Society and is not withdrawable but may be transferred to another member upon exit. Dividends are declared annually based on audited financial performance.
            </p>
          </section>

          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              3. Loan Policies & Guarantorship
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              Members are eligible for credit facilities up to three times their active savings balance, subject to appraisal, guarantor backing, and compliance with repayment history. Default on loan repayments will attract statutory recovery procedures against member shares, savings, and guarantors.
            </p>
          </section>

          <section>
            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '10px', color: 'var(--brand-forest)' }}>
              4. Welfare Fund Claims
            </h3>
            <p style={{ color: 'var(--text-muted)' }}>
              Welfare claims are processed in strict accordance with the Welfare Policy guidelines for qualified events including bereavement, hospitalization, and emergency road assistance.
            </p>
          </section>
        </div>
      </main>
      <Footer />
    </div>
  );
}
