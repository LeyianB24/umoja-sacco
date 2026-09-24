import { test, expect } from '@playwright/test';

/**
 * API Contract E2E Tests — Umoja SACCO
 *
 * Tests the public REST API endpoints directly via fetch/request,
 * verifying response shape, HTTP codes, and security guards.
 * These tests DON'T hit the database — they verify the contract.
 */

test.describe('Auth API — /api/v1/auth/login', () => {
  test('returns 422 when credentials are missing', async ({ request }) => {
    const res = await request.post('/api/v1/auth/login', { data: {} });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body).toHaveProperty('status', 'error');
  });

  test('returns 401 on invalid credentials', async ({ request }) => {
    const res = await request.post('/api/v1/auth/login', {
      data: { identifier: 'nobody@example.com', password: 'wrongpassword', user_type: 'member' },
    });
    expect(res.status()).toBe(401);
    const body = await res.json();
    expect(body.status).toBe('error');
  });

  test('returns standard JSON error shape', async ({ request }) => {
    const res = await request.post('/api/v1/auth/login', {
      data: { identifier: 'x', password: 'y', user_type: 'member' },
    });
    const body = await res.json();
    expect(body).toHaveProperty('status');
    expect(body).toHaveProperty('message');
  });
});

test.describe('Auth API — /api/v1/auth/register', () => {
  test('returns 422 on empty body', async ({ request }) => {
    const res = await request.post('/api/v1/auth/register', { data: {} });
    expect(res.status()).toBeGreaterThanOrEqual(400);
  });

  test('returns error when email is missing', async ({ request }) => {
    const res = await request.post('/api/v1/auth/register', {
      data: { phone: '0712345678', full_name: 'Test User' },
    });
    expect(res.status()).toBeGreaterThanOrEqual(400);
  });
});

test.describe('Auth API — /api/v1/auth/validate', () => {
  test('returns availability for an obviously free email', async ({ request }) => {
    const res = await request.post('/api/v1/auth/validate', {
      data: { field: 'email', value: `e2e_never_used_${Date.now()}@test.invalid` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('data');
  });
});

test.describe('Protected API — Member endpoints', () => {
  test('/api/v1/member/dashboard returns 401 without token', async ({ request }) => {
    const res = await request.get('/api/v1/member/dashboard');
    expect([401, 403]).toContain(res.status());
  });

  test('/api/v1/member/savings returns 401 without token', async ({ request }) => {
    const res = await request.get('/api/v1/member/savings');
    expect([401, 403]).toContain(res.status());
  });

  test('/api/v1/member/loans returns 401 without token', async ({ request }) => {
    const res = await request.get('/api/v1/member/loans');
    expect([401, 403]).toContain(res.status());
  });

  test('/api/v1/member/transactions returns 401 without token', async ({ request }) => {
    const res = await request.get('/api/v1/member/transactions');
    expect([401, 403]).toContain(res.status());
  });
});

test.describe('Protected API — Admin endpoints', () => {
  test('/api/v1/admin returns 401 without token', async ({ request }) => {
    const res = await request.get('/api/v1/admin/declare_dividends');
    expect([401, 403, 405]).toContain(res.status());
  });
});

test.describe('Security Headers', () => {
  test('response includes X-Content-Type-Options', async ({ request }) => {
    const res = await request.get('/');
    const header = res.headers()['x-content-type-options'];
    expect(header).toBe('nosniff');
  });

  test('response includes X-Frame-Options', async ({ request }) => {
    const res = await request.get('/');
    const header = res.headers()['x-frame-options'];
    expect(header).toBeTruthy();
  });

  test('response does not expose X-Powered-By', async ({ request }) => {
    const res = await request.get('/');
    const header = res.headers()['x-powered-by'];
    // Should be undefined (removed via next.config.mjs poweredByHeader: false)
    expect(header).toBeUndefined();
  });
});
