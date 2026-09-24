import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Privacy Policy & Data Protection',
  description: 'How Umoja Sacco protects member personal information, financial data, and transactions under the Kenya Data Protection Act.',
  alternates: {
    canonical: '/privacy',
  },
  openGraph: {
    title: 'Privacy Policy | Umoja Sacco',
    description: 'Learn how Umoja Sacco protects member data and adheres to Kenya Data Protection Act standards.',
    url: '/privacy',
  },
};

export default function PrivacyLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
