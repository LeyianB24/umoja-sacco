import { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://umojasacco.co.ke';

  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: [
          '/admin/',
          '/member/',
          '/api/',
          '/_next/',
          '/forgot-password',
        ],
      },
      {
        userAgent: 'Googlebot',
        allow: '/',
        disallow: [
          '/admin/',
          '/member/',
          '/api/',
          '/_next/',
          '/forgot-password',
        ],
      },
    ],
    sitemap: `${baseUrl}/sitemap.xml`,
  };
}
