/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          {
            key: 'X-DNS-Prefetch-Control',
            value: 'on',
          },
          {
            key: 'Strict-Transport-Security',
            value: 'max-age=63072000; includeSubDomains; preload',
          },
          {
            key: 'X-Frame-Options',
            value: 'SAMEORIGIN',
          },
          {
            key: 'X-Content-Type-Options',
            value: 'nosniff',
          },
          {
            key: 'Referrer-Policy',
            value: 'strict-origin-when-cross-origin',
          },
          {
            key: 'Permissions-Policy',
            value: 'camera=(), microphone=(), geolocation=()',
          },
        ],
      },
    ];
  },
  async rewrites() {
    return [
      {
        source: '/callback_url',
        destination: '/api/v1/public/mpesa_callback',
      },
      {
        source: '/callback_url.php',
        destination: '/api/v1/public/mpesa_callback',
      },
      {
        source: '/public/member/mpesa_callback.php',
        destination: '/api/v1/public/mpesa_callback',
      },
      {
        source: '/api/v1/public/mpesa/callback',
        destination: '/api/v1/public/mpesa_callback',
      },
      {
        source: '/login.php',
        destination: '/login',
      },
      {
        source: '/register.php',
        destination: '/register',
      },
      {
        source: '/forgot_password.php',
        destination: '/forgot-password',
      },
    ];
  },
};

export default nextConfig;
