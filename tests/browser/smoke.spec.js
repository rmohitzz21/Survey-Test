// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Survey Pacific portal — browser smoke (read-mostly).
 * Does not submit leave, approve records, or mutate business data.
 *
 * Auth credentials (optional): SP_ADMIN_EMAIL / SP_ADMIN_PASSWORD,
 * SP_MEMBER_EMAIL / SP_MEMBER_PASSWORD via env or tests/browser/.env
 */

function attachPageErrorCollector(page) {
  /** @type {string[]} */
  const errors = [];
  page.on('pageerror', (err) => {
    errors.push(String(err && err.message ? err.message : err));
  });
  return errors;
}

function creds(role) {
  if (role === 'admin') {
    return {
      email: process.env.SP_ADMIN_EMAIL || '',
      password: process.env.SP_ADMIN_PASSWORD || '',
    };
  }
  return {
    email: process.env.SP_MEMBER_EMAIL || '',
    password: process.env.SP_MEMBER_PASSWORD || '',
  };
}

async function fillLogin(page, email, password) {
  const form = page.locator('form').filter({ has: page.locator('input[type="email"]') }).first();
  await expect(form).toBeVisible();
  await form.locator('input[type="email"]').fill(email);
  await form.locator('input[autocomplete="current-password"]').fill(password);
  await form.locator('button[type="submit"]').click();
}

test.describe('Survey Pacific browser smoke', () => {
  test('login page loads and form is usable', async ({ page }) => {
    const errors = attachPageErrorCollector(page);
    await page.goto('login.php', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveTitle(/Survey Pacific/i);
    await expect(page.getByRole('heading', { name: /Employee Login/i })).toBeVisible();

    const form = page.locator('form').filter({ has: page.locator('input[type="email"]') }).first();
    await expect(form.locator('input[type="email"]')).toBeVisible();
    await expect(form.locator('input[autocomplete="current-password"]')).toBeVisible();
    await expect(form.locator('button[type="submit"]')).toBeVisible();

    // Usable: type without submitting (no credentials required).
    await form.locator('input[type="email"]').fill('smoke-check@example.com');
    await expect(form.locator('input[type="email"]')).toHaveValue('smoke-check@example.com');

    expect(errors, `Unexpected page errors: ${errors.join(' | ')}`).toEqual([]);
  });

  test('unauthenticated portal redirects to login', async ({ page }) => {
    const errors = attachPageErrorCollector(page);
    await page.goto('index.php', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/login\.php/i);
    await expect(page.getByRole('heading', { name: /Employee Login/i })).toBeVisible();
    expect(errors, `Unexpected page errors: ${errors.join(' | ')}`).toEqual([]);
  });

  test('portal CSS and fetch-session-guard assets respond OK', async ({ request }) => {
    const css = await request.get('assets/css/portal.css?v=1');
    expect(css.ok(), `portal.css HTTP ${css.status()}`).toBeTruthy();
    const cssType = (css.headers()['content-type'] || '').toLowerCase();
    expect(cssType).toMatch(/css|text\/plain/);

    const js = await request.get('assets/js/fetch-session-guard.js?v=1');
    expect(js.ok(), `fetch-session-guard.js HTTP ${js.status()}`).toBeTruthy();
    const body = await js.text();
    expect(body).toContain('window.fetch');
    expect(body).toContain('login.php?expired=1');
  });

  test('authenticated admin portal loads when credentials are set', async ({ page }) => {
    const { email, password } = creds('admin');
    test.skip(!email || !password, 'Set SP_ADMIN_EMAIL and SP_ADMIN_PASSWORD to run authenticated admin smoke');

    const errors = attachPageErrorCollector(page);
    await page.goto('login.php', { waitUntil: 'domcontentloaded' });
    await fillLogin(page, email, password);
    await page.waitForURL(/index\.php/i, { timeout: 20_000 });

    await expect(page.locator('body[x-data="spApp()"]')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('link[href*="assets/css/portal.css"]')).toHaveCount(1);
    await expect(page.locator('script[src*="assets/js/fetch-session-guard.js"]')).toHaveCount(1);
    await expect(page.getByRole('heading').first()).toBeVisible();

    expect(errors, `Unexpected page errors: ${errors.join(' | ')}`).toEqual([]);
  });

  test('authenticated member portal loads when credentials are set', async ({ page }) => {
    const { email, password } = creds('member');
    test.skip(!email || !password, 'Set SP_MEMBER_EMAIL and SP_MEMBER_PASSWORD to run authenticated member smoke');

    const errors = attachPageErrorCollector(page);
    await page.goto('login.php', { waitUntil: 'domcontentloaded' });
    await fillLogin(page, email, password);
    await page.waitForURL(/index\.php/i, { timeout: 20_000 });

    await expect(page.locator('body[x-data="spApp()"]')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('link[href*="assets/css/portal.css"]')).toHaveCount(1);
    await expect(page.locator('script[src*="assets/js/fetch-session-guard.js"]')).toHaveCount(1);

    expect(errors, `Unexpected page errors: ${errors.join(' | ')}`).toEqual([]);
  });
});
