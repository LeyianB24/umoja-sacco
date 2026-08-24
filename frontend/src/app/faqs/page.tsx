'use client';

import React, { useState } from 'react';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';
import { ChevronDown, HelpCircle } from 'lucide-react';

export default function FAQsPage() {
  const [openFaq, setOpenFaq] = useState<number | null>(0);

  const faqs = [
    {
      q: 'How do I deposit funds via M-Pesa?',
      a: 'Log into your Member Portal and navigate to "Pay Via M-Pesa". Enter your registered Safaricom phone number and amount. You will receive an instant STK push prompt on your handset to enter your M-Pesa PIN. Your account ledger will be updated immediately upon confirmation.',
    },
    {
      q: 'What is the maximum loan limit I can qualify for?',
      a: 'Members in good standing can apply for up to three (3) times their total cumulative savings balance. The maximum loan duration ranges from 1 to 36 months depending on the loan category (Emergency vs Development).',
    },
    {
      q: 'When and how are annual dividends paid?',
      a: 'Dividends are declared following the Annual General Meeting (AGM) and audited financial sign-off. Approved dividends are credited pro-rata directly to each qualifying member\'s Sacco wallet or savings account.',
    },
    {
      q: 'Can I withdraw my savings balance at any time?',
      a: 'Voluntary savings can be withdrawn through the portal provided the member has no active defaulted loans and is not currently guaranteeing another member\'s loan.',
    },
    {
      q: 'How do I submit a Welfare Claim?',
      a: 'Go to the "Welfare Hub" in your Member Portal and click "Submit Claim". Select the event category (Medical, Bereavement, Emergency Road Breakdown), enter the amount, and provide supporting details.',
    },
    {
      q: 'What if I forget my portal password?',
      a: 'Click "Forgot Password?" on the sign-in page, enter your registered email address, and follow the link sent to your inbox to reset your password securely.',
    },
  ];

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <Navbar />
      <main style={{ flex: 1, maxWidth: '880px', margin: '0 auto', padding: '60px 24px 80px' }}>
        <div style={{ textAlign: 'center', marginBottom: '48px' }}>
          <span className="eyebrow-pill" style={{ marginBottom: '12px' }}>
            <HelpCircle size={14} /> Knowledge Base
          </span>
          <h1 style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--text-main)' }}>
            Frequently Asked Questions
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '1rem', marginTop: '6px' }}>
            Find answers to common questions about membership, loans, savings, and welfare.
          </p>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          {faqs.map((faq, idx) => {
            const isOpen = openFaq === idx;
            return (
              <div
                key={idx}
                style={{
                  backgroundColor: 'var(--bg-surface)',
                  borderRadius: '16px',
                  border: '1px solid var(--border-color)',
                  overflow: 'hidden',
                }}
              >
                <button
                  onClick={() => setOpenFaq(isOpen ? null : idx)}
                  style={{
                    width: '100%',
                    padding: '20px 24px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    textAlign: 'left',
                    fontWeight: 700,
                    fontSize: '1.02rem',
                    color: 'var(--text-main)',
                  }}
                >
                  <span>{faq.q}</span>
                  <ChevronDown
                    size={20}
                    style={{
                      color: 'var(--text-muted)',
                      transform: isOpen ? 'rotate(180deg)' : 'rotate(0)',
                      transition: 'transform 0.2s',
                    }}
                  />
                </button>
                {isOpen && (
                  <div style={{ padding: '0 24px 20px', color: 'var(--text-muted)', fontSize: '0.92rem', lineHeight: 1.6 }}>
                    {faq.a}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </main>
      <Footer />
    </div>
  );
}
