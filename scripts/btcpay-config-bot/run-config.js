/**
 * Core logic: run BTCPay Lightning config for a single connection.
 * Used by index.js (single run) and poll.js (batch).
 */

import { chromium } from 'playwright';
import { logger } from './logger.js';

export async function runConfigForConnection(connectionId) {
  const panelUrl = (process.env.PANEL_URL || process.env.BTCPAY_BOT_PANEL_URL || process.env.APP_URL || '')?.replace(/\/$/, '');
  const panelToken = (process.env.PANEL_BOT_TOKEN || '').trim();
  const panelPassword = (process.env.PANEL_BOT_PASSWORD || '').trim();
  const btcpayUrl = process.env.BTCPAY_BASE_URL?.replace(/\/$/, '');
  const btcpayEmail = process.env.BTCPAY_BOT_EMAIL;
  const btcpayPassword = process.env.BTCPAY_BOT_PASSWORD;

  if (!panelUrl || !panelToken || !panelPassword || !btcpayUrl || !btcpayEmail || !btcpayPassword) {
    throw new Error('Missing required env vars');
  }

  const apiBase = `${panelUrl}/api`;

  // Step 1: Reveal secret from panel
  logger.info('panel_reveal', 'Calling panel reveal API', { connectionId });
  const revealRes = await fetch(`${apiBase}/support/wallet-connections/${connectionId}/reveal`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${panelToken}`,
    },
    body: JSON.stringify({ password: panelPassword }),
  });

  const revealBody = await revealRes.json().catch(() => ({}));

  if (!revealRes.ok) {
    throw new Error(`Panel reveal failed: ${revealRes.status} ${JSON.stringify(revealBody)}`);
  }

  const { secret, btcpay_store_id, store_name } = revealBody?.data ?? {};
  if (!secret || !btcpay_store_id) {
    throw new Error(`Invalid reveal response: ${JSON.stringify(revealBody)}`);
  }

  logger.info('panel_reveal', 'Secret revealed', { storeName: store_name, btcpayStoreId: btcpay_store_id });

  // Step 2: BTCPay UI
  const browser = await chromium.launch({
    headless: process.env.BTCPAY_BOT_HEADLESS !== 'false',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const context = await browser.newContext({
    ignoreHTTPSErrors: process.env.BTCPAY_BOT_IGNORE_HTTPS_ERRORS === 'true',
  });

  const page = await context.newPage();

  page.on('pageerror', (err) => {
    logger.warn('btcpay_page_error', 'BTCPay page error', { error: err.message });
  });

  try {
    const loginUrl = `${btcpayUrl}/login`;
    logger.info('btcpay_login', 'Logging into BTCPay', { url: loginUrl });
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 30000 });

    const emailSel = 'input[name="email"], input[type="email"], #Email';
    const passSel = 'input[name="password"], input[type="password"], #Password';
    await page.waitForSelector(`${emailSel}, ${passSel}`, { timeout: 10000 });
    await page.fill(emailSel, btcpayEmail);
    await page.fill(passSel, btcpayPassword);
    await page.click('button[type="submit"], input[type="submit"], button:has-text("Log in"), button:has-text("Login")');
    await page.waitForLoadState('networkidle');

    // "Logout" appears when logged in – don't treat as error
    const loginError = await page.locator('.alert-danger, .text-danger').first().textContent().catch(() => null);
    const err = loginError?.trim();
    if (err && !err.toLowerCase().includes('logout')) {
      throw new Error(`BTCPay login failed: ${err}`);
    }

    const lightningUrl = `${btcpayUrl}/stores/${btcpay_store_id}/lightning/BTC/setup`;
    logger.info('btcpay_lightning', 'Navigating to Lightning setup', { url: lightningUrl });
    await page.goto(lightningUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await new Promise((r) => setTimeout(r, 4000)); // let Blazor/JS render (slower in headless)

    try {
      await page.waitForSelector('#LightningNodeType-Custom', { state: 'attached', timeout: 45000 });
    } catch (e) {
      const currentUrl = page.url();
      const bodyText = await page.locator('body').innerText().catch(() => '');
      logger.error('btcpay_lightning_no_form', 'Lightning setup form not found', {
        currentUrl,
        bodySnippet: bodyText.slice(0, 300),
      });
      throw new Error(`Lightning setup form not found. Page: ${currentUrl}. Check access (403?) or BTCPay version.`);
    }

    // Switch to "Use custom node" tab – try label first, then Bootstrap Tab API (BTCPay versions differ)
    const customLabel = page.locator('label[for="LightningNodeType-Custom"]');
    const labelVisible = await customLabel.isVisible().catch(() => false);
    if (labelVisible) {
      logger.info('btcpay_lightning', 'Clicking Use custom node (label)');
      await customLabel.click();
    } else {
      logger.info('btcpay_lightning', 'Switching to Use custom node (Bootstrap Tab)');
      await page.evaluate(() => {
        const tabTrigger = document.querySelector('#LightningNodeType-Custom');
        if (!tabTrigger) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
          const tab = bootstrap.Tab.getOrCreateInstance(tabTrigger);
          tab.show();
        } else {
          tabTrigger.checked = true;
          tabTrigger.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
      await page.waitForSelector('#CustomSetup.tab-pane.show', { state: 'visible', timeout: 5000 }).catch(() => {});
      await new Promise((r) => setTimeout(r, 400));
    }
    await page.locator('#LightningNodeType-Custom').check(); // ensure form submits Custom
    await page.waitForSelector('#ConnectionString', { state: 'visible', timeout: 15000 });

    await page.fill('#ConnectionString', secret);
    await new Promise((r) => setTimeout(r, 300));

    logger.info('btcpay_lightning', 'Clicking Save');
    await page.click('#page-primary');
    await page.waitForLoadState('networkidle');

    const btcpayError = await page.locator('.alert-danger, .text-danger, [role="alert"]').first().textContent().catch(() => null);
    const saveErr = btcpayError?.trim();
    if (saveErr && !saveErr.toLowerCase().includes('logout')) {
      throw new Error(`BTCPay save error: ${saveErr}`);
    }

    logger.info('btcpay_lightning', 'Lightning setup completed');
  } finally {
    await browser.close();
  }

  // Step 3: Mark connected in panel
  logger.info('panel_mark_connected', 'Marking connected');
  const markRes = await fetch(`${apiBase}/support/wallet-connections/${connectionId}/mark-connected`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${panelToken}`,
    },
  });

  const markBody = await markRes.json().catch(() => ({}));
  if (!markRes.ok) {
    logger.error('panel_mark_connected_failed', 'Mark connected failed', {
      status: markRes.status,
      body: markBody,
    });
    throw new Error(`Mark connected failed: ${markRes.status} ${JSON.stringify(markBody)}`);
  }

  logger.info('bot_done', 'Completed successfully', { connectionId, storeName: store_name });
  return { connectionId, storeName: store_name };
}
