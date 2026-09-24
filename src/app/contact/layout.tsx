import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Contact Us & Member Helpdesk',
  description: 'Reach Umoja Sacco member support desk, call our toll-free line 0800 000 786, or visit our Nairobi offices.',
  alternates: {
    canonical: '/contact',
  },
  openGraph: {
    title: 'Contact Umoja Sacco | Member Helpdesk',
    description: 'Get in touch with Umoja Sacco support. Toll-free: 0800 000 786. Nairobi, Kenya.',
    url: '/contact',
  },
};

export default function ContactLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
