import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E configuration for Umoja SACCO Management System.
 *
 * Local:  BASE_URL defaults to http://localhost:3000
 * CI:     Set BASE_URL env var to the preview/staging URL.
 *         Uses 2 retries, always saves traces & screenshots on failure.
 */

const BASE_URL = process.env.BASE_URL || 'http://localhost:3000';
const IS_CI = !!process.env.CI;

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: IS_CI,
  retries: IS_CI ? 2 : 0,
  workers: IS_CI ? 2 : undefined,
  reporter: IS_CI
    ? [['github'], ['html', { outputFolder: 'playwright-report', open: 'never' }]]
    : [['list'], ['html', { outputFolder: 'playwright-report', open: 'on-failure' }]],

  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },

  projects: [
    // ── Setup: seed auth state ──────────────────────────────────────────────
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },

    // ── Main browser matrix ─────────────────────────────────────────────────
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'e2e/.auth/member.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        storageState: 'e2e/.auth/member.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'mobile-chrome',
      use: {
        ...devices['Pixel 5'],
        storageState: 'e2e/.auth/member.json',
      },
      dependencies: ['setup'],
    },

    // ── Public (no auth) tests run without storage state ────────────────────
    {
      name: 'public',
      testMatch: /.*\.public\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Start the dev server automatically when running locally
  webServer: IS_CI
    ? undefined
    : {
        command: 'npm run dev',
        url: BASE_URL,
        reuseExistingServer: true,
        timeout: 120_000,
      },
});
