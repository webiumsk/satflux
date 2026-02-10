/**
 * Structured logger for BTCPay config bot.
 * Logs all actions, outputs, and errors to stdout and optionally to a file.
 * Format: JSON lines for easy parsing and grepping.
 */

import { createWriteStream } from 'fs';
import { dirname } from 'path';
import { fileURLToPath } from 'url';
import { mkdirSync, existsSync } from 'fs';

const __dirname = dirname(fileURLToPath(import.meta.url));

let logFileStream = null;

/**
 * Get log file path from env or default
 */
function getLogFilePath() {
  return process.env.BTCPAY_BOT_LOG_FILE || '/tmp/btcpay-config-bot.log';
}

/**
 * Ensure log directory exists and open stream
 */
function ensureLogFile() {
  if (logFileStream) return logFileStream;
  const logPath = getLogFilePath();
  const dir = dirname(logPath);
  if (!existsSync(dir)) {
    mkdirSync(dir, { recursive: true });
  }
  logFileStream = createWriteStream(logPath, { flags: 'a' });
  return logFileStream;
}

/**
 * Write a log entry
 * @param {string} level - info | warn | error
 * @param {string} action - short action identifier (e.g. "panel_reveal", "btcpay_login")
 * @param {string} message - human-readable message
 * @param {object} [data] - optional extra data (errors, response codes, etc.)
 */
export function log(level, action, message, data = {}) {
  const entry = {
    timestamp: new Date().toISOString(),
    level,
    action,
    message,
    ...(Object.keys(data).length ? { data } : {}),
  };
  const line = JSON.stringify(entry) + '\n';
  process.stdout.write(line);

  try {
    const stream = ensureLogFile();
    stream.write(line);
  } catch (err) {
    process.stderr.write(JSON.stringify({
      timestamp: new Date().toISOString(),
      level: 'error',
      action: 'logger',
      message: 'Failed to write to log file',
      data: { error: err.message },
    }) + '\n');
  }
}

export const logger = {
  info(action, message, data) { log('info', action, message, data || {}); },
  warn(action, message, data) { log('warn', action, message, data || {}); },
  error(action, message, data) { log('error', action, message, data || {}); },
};
