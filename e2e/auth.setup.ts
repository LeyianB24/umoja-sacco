import { test as setup } from '@playwright/test';
import path from 'path';

const MEMBER_AUTH_FILE = path.join(__dirname, '.auth/member.json');

/**
 * Auth Setup: Logs in as a test member and saves the storage state (cookies + localStorage)
 * so subsequent tests don't repeat the login flow.
 *
 * Credentials are pulled from environment variables — set them in .env.test or CI secrets:
 *   E2E_MEMBER_EMAIL=testmember@example.com
 *   E2E_MEMBER_PASSWORD=TestPassword123!
 */
setup('authenticate as member', async ({ page }) => {
  const email = process.env.E2E_MEMBER_EMAIL || 'testmember@example.com';
  const password = process.env.E2E_MEMBER_PASSWORD || 'TestPassword123!';

  await page.goto('/login');

  // Select Member Portal tab (already default, but be explicit)
  await page.getByTestId('login-tab-member').click();

  await page.getByTestId('login-identifier').fill(email);
  await page.getByTestId('login-password').fill(password);
  await page.getByTestId('login-submit').click();

  // Wait for navigation to member dashboard
  await page.waitForURL('**/member**', { timeout: 15_000 });

  // Save authentication state
  await page.context().storageState({ path: MEMBER_AUTH_FILE });
});
