import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Terms of Service & Cooperative By-Laws',
  description: 'Official terms of membership, savings covenants, borrowing guidelines, and SASRA regulatory by-laws of Umoja Sacco Society Ltd.',
  alternates: {
    canonical: '/terms',
  },
  openGraph: {
    title: 'Terms of Service | Umoja Sacco',
    description: 'Official cooperative terms of service and member guidelines.',
    url: '/terms',
  },
};

export default function TermsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
