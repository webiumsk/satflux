/**
 * Core logic: run BTCPay Lightning config for a single connection.
 * Used by index.js (single run) and poll.js (batch).
 */

import { chromium } from 'playwright';
import { logger } from './logger.js';

export async function runConfigForConnection(connectionId) {
  const panelUrl = (process.env.PANEL_URL || process.env.BTCPAY_BOT_PANEL_URL || process.env.APP_URL || '')?.replace(/\/$/, '');
  const panelToken = process.env.PANEL_BOT_TOKEN;
  const panelPassword = process.env.PANEL_BOT_PASSWORD;
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

    const loginError = await page.locator('.alert-danger, .text-danger, [role="alert"]').first().textContent().catch(() => null);
    if (loginError) {
      throw new Error(`BTCPay login failed: ${loginError.trim()}`);
    }

    const lightningUrl = `${btcpayUrl}/stores/${btcpay_store_id}/lightning/BTC/setup`;
    logger.info('btcpay_lightning', 'Navigating to Lightning setup', { url: lightningUrl });
    await page.goto(lightningUrl, { waitUntil: 'networkidle', timeout: 30000 });

    const customNodeSel = '#LightningNodeType-Custom, label[for="LightningNodeType-Custom"]';
    const customEl = page.locator(customNodeSel).first();
    if (await customEl.isVisible().catch(() => false)) {
      logger.info('btcpay_lightning', 'Clicking Use custom node');
      await customEl.click();
    }

    const connSel = '#ConnectionString, textarea[name="ConnectionString"], [id*="connection"], textarea';
    await page.waitForSelector(connSel, { state: 'visible', timeout: 15000 });
    await page.fill(connSel, secret);

    logger.info('btcpay_lightning', 'Clicking Save');
    const saveSel = '#page-primary, button:has-text("Save"), button[type="submit"]';
    await page.click(saveSel);
    await page.waitForLoadState('networkidle');

    const btcpayError = await page.locator('.alert-danger, .text-danger, [role="alert"]').first().textContent().catch(() => null);
    if (btcpayError) {
      throw new Error(`BTCPay save error: ${btcpayError.trim()}`);
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

  if (!markRes.ok) {
    const markBody = await markRes.json().catch(() => ({}));
    throw new Error(`Mark connected failed: ${markRes.status} ${JSON.stringify(markBody)}`);
  }

  logger.info('bot_done', 'Completed successfully', { connectionId, storeName: store_name });
  return { connectionId, storeName: store_name };
}
