#!/usr/bin/env node
/**
 * BTCPay Config Bot - Poller
 *
 * Runs on the HOST (not in Docker). Polls panel for pending wallet connections
 * and configures each via BTCPay UI. No Docker network or permission issues.
 *
 * Usage:
 *   node poll.js              # poll every 2 min
 *   node poll.js --once       # run once and exit
 *   node poll.js --interval 60  # poll every 60 seconds
 *
 * Requires: project root .env, .env.production, and/or .env.standalone with panel and BTCPay credentials (see README.md)
 * Panel URL: use APP_URL (e.g. http://localhost:8080) - from host, localhost works
 */

import { config } from 'dotenv';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { logger } from './logger.js';
import { runConfigForConnection } from './run-config.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '../..');
config({ path: resolve(root, '.env') });
config({ path: resolve(root, '.env.production') });
// Host deployments that keep secrets only in .env.standalone (gitignored; see docker-compose.standalone)
config({ path: resolve(root, '.env.standalone') });
config();

const args = process.argv.slice(2);
const once = args.includes('--once');
const intervalIdx = args.indexOf('--interval');
const intervalSec = intervalIdx >= 0 ? parseInt(args[intervalIdx + 1], 10) || 120 : 120;

const panelUrl = (process.env.PANEL_URL || process.env.BTCPAY_BOT_PANEL_URL || process.env.APP_URL || '')?.replace(/\/$/, '');
const panelToken = (process.env.PANEL_BOT_TOKEN || '').trim();

/** Fetch connections for the bot to process: only "pending" (new). needs_support = manual, bot does not retry. */
async function fetchPendingConnections() {
  const apiBase = `${panelUrl}/api`;
  const res = await fetch(`${apiBase}/support/wallet-connections?status=pending`, {
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${panelToken}`,
    },
  });

  if (!res.ok) {
    throw new Error(`Failed to fetch connections: ${res.status}`);
  }

  const body = await res.json();
  const list = body?.data ?? [];
  return Array.isArray(list) ? list : [];
}

async function runPoll() {
  if (!panelUrl || !panelToken) {
    logger.error('poll_config', 'Missing panel URL (PANEL_URL, BTCPAY_BOT_PANEL_URL, or APP_URL in .env / .env.production / .env.standalone) or PANEL_BOT_TOKEN', {});
    process.exit(1);
  }

  logger.info('poll_start', 'Fetching pending connections (bot only processes pending)', { panelUrl });

  const connections = await fetchPendingConnections();
  logger.info('poll_fetched', `Found ${connections.length} pending connection(s)`, { count: connections.length });

  for (const conn of connections) {
    const id = conn.id;
    const storeName = conn.store_name ?? 'Unknown';
    logger.info('poll_process', 'Processing', { connectionId: id, storeName });

    try {
      await runConfigForConnection(id);
    } catch (err) {
      logger.error('poll_error', `Failed for ${id}`, { connectionId: id, error: err.message });
      try {
        const apiBase = `${panelUrl}/api`;
        const reportRes = await fetch(`${apiBase}/support/wallet-connections/${id}/bot-failed`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${panelToken}`,
          },
          body: JSON.stringify({ error: err.message || String(err) }),
        });
        if (reportRes.ok) {
          logger.info('poll_bot_failed_reported', 'Reported bot failure to panel', { connectionId: id });
        } else {
          logger.warn('poll_bot_failed_report_failed', 'Could not report bot failure', { connectionId: id, status: reportRes.status });
        }
      } catch (reportErr) {
        logger.warn('poll_bot_failed_report_err', 'Error reporting bot failure', { connectionId: id, error: reportErr.message });
      }
    }
  }

  if (once) {
    logger.info('poll_done', 'One-shot complete', { processed: connections.length });
    process.exit(0);
  }
}

async function main() {
  const loop = async () => {
    try {
      await runPoll();
    } catch (err) {
      logger.error('poll_loop', 'Poll cycle error', { error: err.message });
    }
    if (!once) {
      setTimeout(loop, intervalSec * 1000);
    }
  };

  await loop();
}

main();
