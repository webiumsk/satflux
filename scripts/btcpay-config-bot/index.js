#!/usr/bin/env node
/**
 * BTCPay Lightning Config Bot - Single run
 *
 * Run for one connection. Use poll.js for continuous polling.
 *
 *   node index.js <connection_id>
 *
 * Run on HOST for simplicity (localhost works for panel).
 * Requires: .env with panel and BTCPay credentials.
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
config();

const connectionId = process.argv[2] || process.env.BTCPAY_BOT_CONNECTION_ID;

if (!connectionId) {
  console.error('Usage: node index.js <connection_id>');
  process.exit(1);
}

logger.info('bot_start', 'Starting', { connectionId });

runConfigForConnection(connectionId).catch((err) => {
  logger.error('bot_error', err.message, { error: err.message, stack: err.stack });
  process.exit(1);
});
