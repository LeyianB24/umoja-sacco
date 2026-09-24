import type { Metadata } from 'next';
import '@/styles/globals.css';
import { AuthProvider } from '@/context/AuthContext';
import { ThemeProvider } from '@/context/ThemeContext';
import { ToastProvider } from '@/context/ToastContext';

const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://umojasacco.co.ke';

export const metadata: Metadata = {
  metadataBase: new URL(baseUrl),
  title: {
    default: 'Umoja Sacco — Savings & Credit Cooperative Society',
    template: '%s | Umoja Sacco',
  },
  description: 'Empowering drivers, operators, and allied professionals across Kenya through transparent savings, affordable credit, and welfare solidarity. SASRA Registered.',
  keywords: [
    'Umoja Sacco',
    'Kenya Sacco',
    'Savings and Credit Kenya',
    'Matatu Sacco',
    'Transport Sacco',
    'SASRA Registered Sacco',
    'Emergency Loans Kenya',
    'Asset Financing Nairobi',
    'Bezalel Technologies',
  ],
  authors: [{ name: 'Bezalel Technologies', url: 'https://www.bezalel.website/' }],
  creator: 'Bezalel Technologies',
  publisher: 'Umoja Sacco Society Ltd',
  alternates: {
    canonical: '/',
  },
  openGraph: {
    title: 'Umoja Sacco — Savings & Credit Cooperative Society',
    description: 'Empowering drivers, operators, and allied professionals across Kenya through transparent savings, affordable credit, and welfare solidarity.',
    url: baseUrl,
    siteName: 'Umoja Sacco Society Ltd',
    locale: 'en_KE',
    type: 'website',
    images: [
      {
        url: '/assets/images/people_logo.png',
        width: 800,
        height: 600,
        alt: 'Umoja Sacco Official Seal',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Umoja Sacco — Savings & Credit Cooperative Society',
    description: 'Empowering drivers, operators, and allied professionals across Kenya through transparent savings, affordable credit, and welfare solidarity.',
    images: ['/assets/images/people_logo.png'],
    creator: '@UmojaSaccoKE',
  },
  icons: {
    icon: '/assets/images/people_logo.png',
    shortcut: '/assets/images/people_logo.png',
    apple: '/assets/images/people_logo.png',
  },
  robots: {
    index: true,
    follow: true,
  },
};

const jsonLdSchema = {
  '@context': 'https://schema.org',
  '@type': 'FinancialService',
  name: 'Umoja Sacco Society Ltd',
  alternateName: 'Umoja Savings and Credit Cooperative Society',
  url: baseUrl,
  logo: `${baseUrl}/assets/images/people_logo.png`,
  description: 'SASRA-regulated Savings and Credit Cooperative Society providing vehicle financing, emergency credit, dividends, and welfare funds in Kenya.',
  telephone: '+254-800-000-786',
  email: 'info@umojasacco.co.ke',
  address: {
    '@type': 'PostalAddress',
    addressLocality: 'Nairobi',
    addressCountry: 'KE',
  },
  areaServed: 'Kenya',
  currenciesAccepted: 'KES',
  paymentAccepted: 'Cash, M-Pesa, Bank Wire',
  priceRange: 'KES 500 - KES 10,000,000',
  sameAs: [
    'https://www.bezalel.website/',
  ],
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" data-bs-theme="light" suppressHydrationWarning>
      <head>
        <link rel="icon" type="image/png" href="/assets/images/people_logo.png" />
        <link rel="apple-touch-icon" href="/assets/images/people_logo.png" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap"
          rel="stylesheet"
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLdSchema) }}
        />
        <script
          dangerouslySetInnerHTML={{
            __html: `
              (function() {
                try {
                  var saved = localStorage.getItem('theme');
                  var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                  document.documentElement.setAttribute('data-bs-theme', theme);
                  document.documentElement.setAttribute('data-theme', theme);
                  if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                  } else {
                    document.documentElement.classList.remove('dark');
                  }
                } catch (e) {}
              })();
            `,
          }}
        />
      </head>
      <body>
        <ThemeProvider>
          <ToastProvider>
            <AuthProvider>
              {children}
            </AuthProvider>
          </ToastProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
