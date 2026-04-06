# BTCPay config bot

Optional Node automation: opens BTCPay in a headless browser and applies wallet settings after the panel reveals the connection secret via API.

## Security

- **No secrets in this repo.** Credentials come from environment variables. The bot loads `.env`, `.env.production`, then **`.env.standalone`** at the project root (same as standalone Docker deploys), then the process environment.
- **Logs** (`BTCPAY_BOT_LOG_FILE`, default `/tmp/btcpay-config-bot.log`) may contain store names, BTCPay store ids, and errors. Treat log files like credentials; do not commit them. The bot does not log raw wallet secrets.
- **Panel reveal API** responses are not written to logs in full; avoid running with extra debug that prints response bodies.

## Required environment variables

| Variable | Purpose |
|----------|---------|
| `PANEL_URL` or `BTCPAY_BOT_PANEL_URL` or `APP_URL` | Laravel panel base URL |
| `PANEL_BOT_TOKEN` | Bearer token for bot API |
| `PANEL_BOT_PASSWORD` | User password for signed reveal |
| `BTCPAY_BASE_URL` | BTCPay Server URL |
| `BTCPAY_BOT_EMAIL` | BTCPay login email |
| `BTCPAY_BOT_PASSWORD` | BTCPay login password |
| `BTCPAY_BOT_CONNECTION_ID` | (for `index.js`) connection UUID |

Optional: `BTCPAY_BOT_HEADLESS`, `BTCPAY_BOT_IGNORE_HTTPS_ERRORS`, `BTCPAY_BOT_LOG_FILE`, `BTCPAY_BOT_USE_JOB` (see Laravel scheduling if used).

## Commands

```bash
cd scripts/btcpay-config-bot && npm ci
node index.js <connection_id>    # single run
npm run poll                       # continuous poll
npm run poll:once                  # one poll cycle
```

Install Chromium for Playwright: `npx playwright install chromium` (also runs on `npm install` via `postinstall`).

## Other scripts in `/scripts`

| Script | Notes |
|--------|--------|
| `create-subscription-store.example.php` | Copy to `create-subscription-store.php` (gitignored). Requires `SUBSCRIPTION_STORE_ID` in `.env`. |
| `bump-version.js` / `fix-manifest.js` / `install-git-hooks.sh` | Dev tooling; no secrets. |
