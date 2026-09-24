/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,

  // ── Performance ───────────────────────────────────────────────────────────
  compress: true, // Enable gzip/brotli response compression

  // Next.js Image component optimisation: serve modern formats automatically
  images: {
    formats: ['image/avif', 'image/webp'],
    minimumCacheTTL: 86400, // 24h CDN cache for optimised images
    remotePatterns: [],     // Add external image domains here if needed
  },

  // ── Security Headers ──────────────────────────────────────────────────────
  async headers() {
    return [
      {
        // Long-lived cache for all static assets
        source: '/assets/:path*',
        headers: [
          { key: 'Cache-Control', value: 'public, max-age=31536000, immutable' },
        ],
      },
      {
        source: '/:path*',
        headers: [
          { key: 'X-DNS-Prefetch-Control', value: 'on' },
          {
            key: 'Strict-Transport-Security',
            value: 'max-age=63072000; includeSubDomains; preload',
          },
          { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
        ],
      },
    ];
  },

  // ── URL Rewrites (legacy PHP path compat + M-Pesa callback aliases) ───────
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
      { source: '/login.php',           destination: '/login' },
      { source: '/register.php',        destination: '/register' },
      { source: '/forgot_password.php', destination: '/forgot-password' },
    ];
  },
};

export default nextConfig;
