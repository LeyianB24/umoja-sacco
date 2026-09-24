/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
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
