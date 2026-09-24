import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Join Umoja Sacco — Member Registration',
  description: 'Open your Umoja Sacco account online in minutes. Start saving, access affordable credit up to 3x your balance, and earn annual dividends.',
  alternates: {
    canonical: '/register',
  },
  openGraph: {
    title: 'Join Umoja Sacco | Member Registration',
    description: 'Register online to join Umoja Sacco Society Ltd and start building wealth today.',
    url: '/register',
  },
};

export default function RegisterLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
