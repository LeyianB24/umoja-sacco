/** @type {import('next').NextConfig} */
const phpBackend = process.env.PHP_BACKEND_URL || 'http://localhost:8000';

const nextConfig = {
  reactStrictMode: true,
  async rewrites() {
    return [
      {
        source: '/api/v1/:path*',
        destination: `${phpBackend}/api/v1/:path*`,
      },
    ];
  },
};

export default nextConfig;
