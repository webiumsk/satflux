# BTCPay Config Bot

The privileged browser bot handles Aqua/Boltz descriptors only. Blink, Blitz, Flash, Lightning addresses, and NWC use store-authorized merchant APIs. The bot refuses these wallet types and the poller excludes them. See [2.4.5 implementation and deployment](BTCPAY_2_4_5_IMPLEMENTATION.md).

For Aqua/Boltz, the bot:

1. Reveals the connection secret (and type) from the panel API (support auth)
2. Logs into BTCPay and configures Lightning according to connection type:
   - **Aqua (Boltz)**: Configure Boltz → Continue → Import a wallet → Enter core descriptor → fill Wallet Name + Core descriptor → Import
3. Marks the connection as connected in the panel

## Recommended: Run on host (poller)

**Run the bot on the host**, not in Docker. This avoids Docker network and permission issues.

### Setup

1. **Node.js 18+** on the host (not only in Docker)
2. **Install dependencies** (on host):

```bash
cd scripts/btcpay-config-bot
npm install
```

`npm install` pulls Playwright’s Chromium build, but **Chromium still needs host OS libraries**. On a minimal VPS/Docker-host image, the browser may fail immediately with errors like **`libnspr4.so: cannot open shared object file`** (exit code 127).

### Lightning wallet recovery

A rejected replacement keeps the previous local wallet. A lost response creates a durable hold until `btcpay:reconcile-wallet-updates` confirms the desired configuration. Seeing the previous configuration after a timeout does not release the hold; an operator must first verify that no write remains in flight and explicitly use `--store=LOCAL_ID --confirm-rejected`. Do not use the browser bot to bypass an API rejection.

### Run poller (continuous)

```bash
cd scripts/btcpay-config-bot
npm run poll
```

Polls every 2 minutes for **`pending`** wallet connections (`GET .../support/wallet-connections?status=pending`) and configures each. Status `needs_support` is for manual handling; the poller does not fetch it.

Options:

```bash
npm run poll:once       # run once and exit
node poll.js --interval 60   # poll every 60 seconds
```

### Run for one connection (testing)

```bash
cd scripts/btcpay-config-bot
node index.js <connection_uuid>
```

### Cron (optional)

Cron uses a minimal `PATH`; `node` may not resolve to the same binary as in your login shell. Use the full path from `command -v node` on the server (often `/usr/bin/node` after a system Node install).

```cron
*/2 * * * * cd /path/to/satflux/scripts/btcpay-config-bot && /usr/bin/node poll.js --once >> /tmp/btcpay-bot.log 2>&1
```

Ensure **`npm install`** has been run in `scripts/btcpay-config-bot` on that host. The bot reads `/path/to/satflux/.env` plus `.env.production` and **`.env.standalone`** at the project root-not only files inside Docker.

## Alternative: Laravel job (Docker)

If you prefer the Laravel job in Docker:

1. Set `BTCPAY_BOT_USE_JOB=true` in `.env`
2. Set `BTCPAY_BOT_PANEL_URL` so the container can reach the panel (e.g. `http://nginx:80` or `http://172.17.0.1:8080`)
3. Run queue worker: `docker compose exec php php artisan queue:work --queue=btcpay-config`

The poller on host is simpler and avoids Docker networking.

## Logging

- **stdout** - JSON lines
- **Log file** - `BTCPAY_BOT_LOG_FILE` or `/tmp/btcpay-config-bot.log`

If you redirect cron output to another file (e.g. `/tmp/btcpay-bot.log`), shell errors and bot JSON may appear there in addition to the logger file above.

## BTCPay UI assumptions

- No 2FA, no CAPTCHA
- **Blink**: Lightning setup page has "Use custom node" tab; bot fills `#ConnectionString` and clicks Save
- **Aqua/Bull (Boltz)**: When the merchant has a BTCPay API key, Satflux first tries the Boltz plugin **Greenfield API** (`POST .../boltz/wallets` with `coreDescriptor`, then `POST .../boltz/setup`). If that succeeds, the connection is marked `connected` and Playwright is skipped.
- **Aqua/Bull (Boltz) fallback**: Lightning setup → Configure Boltz → wizard (Standalone → Continue → Import wallet → Enter core descriptor). Descriptors with a `#checksum` suffix are stripped before import (Bull Bitcoin and BTCPay plugin v2.3+).
