import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Sign In to Member Portal',
  description: 'Access your Umoja Sacco member dashboard, check savings balances, apply for loans, and monitor dividends.',
  alternates: {
    canonical: '/login',
  },
  robots: {
    index: true,
    follow: false,
  },
};

export default function LoginLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
