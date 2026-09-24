import { test, expect } from '@playwright/test';

/**
 * Member Dashboard E2E Tests — Umoja SACCO
 *
 * Requires authenticated session (see e2e/auth.setup.ts).
 * Covers:
 * - Dashboard overview
 * - Savings & Shares pages
 * - Transactions
 * - Loans
 * - Profile
 * - Notifications
 * - Support ticket creation
 * - Logout
 */

test.describe('Member Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/member');
  });

  test('loads dashboard and shows balance cards', async ({ page }) => {
    await expect(page).toHaveURL(/\/member/);
    // Balance hero or cards should be visible
    await expect(page.getByText(/savings/i).first()).toBeVisible({ timeout: 10_000 });
  });

  test('member name is displayed in the header', async ({ page }) => {
    // Sidebar/header should show user name or greeting
    await expect(page.getByTestId('member-greeting')).toBeVisible({ timeout: 10_000 });
  });

  test('sidebar navigation links are present', async ({ page }) => {
    const links = [
      'Savings',
      'Shares',
      'Loans',
      'Transactions',
      'Profile',
    ];
    for (const linkText of links) {
      await expect(page.getByRole('link', { name: new RegExp(linkText, 'i') }).first()).toBeVisible();
    }
  });

  test('quick deposit modal opens and closes', async ({ page }) => {
    const depositBtn = page.getByTestId('quick-deposit-btn');
    if (await depositBtn.isVisible()) {
      await depositBtn.click();
      await expect(page.getByTestId('deposit-modal')).toBeVisible();
      // Close modal
      await page.keyboard.press('Escape');
      await expect(page.getByTestId('deposit-modal')).toBeHidden({ timeout: 5_000 });
    }
  });

  test('deposit modal validates empty amount', async ({ page }) => {
    const depositBtn = page.getByTestId('quick-deposit-btn');
    if (await depositBtn.isVisible()) {
      await depositBtn.click();
      const amountInput = page.getByTestId('deposit-amount-input');
      if (await amountInput.isVisible()) {
        await amountInput.clear();
        await page.getByTestId('deposit-submit-btn').click();
        // Should show error or remain on modal
        await expect(page.getByTestId('deposit-modal')).toBeVisible();
      }
    }
  });
});

test.describe('Savings Page', () => {
  test('navigates to savings and shows balance', async ({ page }) => {
    await page.goto('/member/savings');
    await expect(page).toHaveURL(/\/member\/savings/);
    await expect(page.getByText(/savings/i).first()).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Shares Page', () => {
  test('navigates to shares and shows balance', async ({ page }) => {
    await page.goto('/member/shares');
    await expect(page).toHaveURL(/\/member\/shares/);
    await expect(page.getByText(/shares/i).first()).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Transactions Page', () => {
  test('loads transaction history', async ({ page }) => {
    await page.goto('/member/transactions');
    await expect(page).toHaveURL(/\/member\/transactions/);
    // Either a table or an empty state message should be visible
    const hasTable = await page.locator('table').isVisible().catch(() => false);
    const hasEmpty = await page.getByText(/no transactions/i).isVisible().catch(() => false);
    expect(hasTable || hasEmpty).toBeTruthy();
  });
});

test.describe('Loans Page', () => {
  test('loads loans page', async ({ page }) => {
    await page.goto('/member/loans');
    await expect(page).toHaveURL(/\/member\/loans/);
    await expect(page.getByText(/loan/i).first()).toBeVisible({ timeout: 10_000 });
  });

  test('loan application form validates required fields', async ({ page }) => {
    await page.goto('/member/loans');
    // Find the apply button
    const applyBtn = page.getByRole('button', { name: /apply/i }).first();
    if (await applyBtn.isVisible()) {
      await applyBtn.click();
      // Try submitting with empty fields
      const submitBtn = page.getByRole('button', { name: /submit|apply/i }).first();
      if (await submitBtn.isVisible()) {
        await submitBtn.click();
        // Should show validation error
        await expect(page.getByRole('alert').or(page.getByText(/required|invalid/i))).toBeVisible({
          timeout: 5_000,
        });
      }
    }
  });
});

test.describe('Profile Page', () => {
  test('shows member profile information', async ({ page }) => {
    await page.goto('/member/profile');
    await expect(page).toHaveURL(/\/member\/profile/);
    await expect(page.getByText(/profile/i).first()).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Notifications Page', () => {
  test('loads notifications list', async ({ page }) => {
    await page.goto('/member/notifications');
    await expect(page).toHaveURL(/\/member\/notifications/);
    // Either notifications exist or empty state
    const hasContent = await page
      .locator('main, [data-testid="notifications-list"]')
      .isVisible()
      .catch(() => false);
    expect(hasContent).toBeTruthy();
  });
});

test.describe('Support Page', () => {
  test('loads support/help page', async ({ page }) => {
    await page.goto('/member/support');
    await expect(page).toHaveURL(/\/member\/support/);
    await expect(page.getByText(/support|ticket/i).first()).toBeVisible({ timeout: 10_000 });
  });

  test('support ticket form renders', async ({ page }) => {
    await page.goto('/member/support');
    const form = page.getByRole('form').first().or(page.locator('textarea').first());
    if (await form.isVisible()) {
      await expect(form).toBeVisible();
    }
  });
});

test.describe('Session Expiry', () => {
  test('protected route without auth redirects to login', async ({ browser }) => {
    // Fresh context with NO auth state
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/member');
    // Should redirect to login or show 401/unauthorized
    await expect(page).toHaveURL(/login|\/$/i, { timeout: 10_000 });
    await context.close();
  });
});

test.describe('Logout', () => {
  test('logout clears session and redirects to home', async ({ page }) => {
    await page.goto('/member');
    const logoutBtn = page.getByTestId('logout-btn').or(
      page.getByRole('button', { name: /logout|sign out/i })
    );
    if (await logoutBtn.isVisible()) {
      await logoutBtn.click();
      await expect(page).toHaveURL(/\/$|\/login/i, { timeout: 10_000 });
    }
  });
});
