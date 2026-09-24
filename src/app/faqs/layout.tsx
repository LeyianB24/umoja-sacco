import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Frequently Asked Questions (FAQs)',
  description: 'Answers to common questions regarding Umoja Sacco membership, M-Pesa deposits, loan eligibility, interest rates, and annual dividends.',
  alternates: {
    canonical: '/faqs',
  },
  openGraph: {
    title: 'Frequently Asked Questions (FAQs) | Umoja Sacco',
    description: 'Find answers to common questions regarding Umoja Sacco membership, M-Pesa deposits, loan eligibility, and dividends.',
    url: '/faqs',
  },
};

export default function FaqsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
