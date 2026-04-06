# BTCPay Config Bot

Automates BTCPay Lightning setup when a wallet connection needs support. The bot:

1. Reveals the connection secret (and type) from the panel API (support auth)
2. Logs into BTCPay and configures Lightning according to connection type:
   - **Blink**: "Use custom node" tab → fill `#ConnectionString` with the connection string → Save
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

3. **Environment** – bot loads, in order (later files override earlier keys): `.env`, `.env.production`, **`.env.standalone`**, then process environment. Use `.env.standalone` on the host if that is where you keep production secrets (same pattern as some Satflux deployments).

| Variable | Description |
|----------|-------------|
| `PANEL_BOT_TOKEN` | Sanctum token for bot (support/admin user in the **panel**, not only BTCPay) |
| `PANEL_BOT_PASSWORD` | Bot user password (if used by automation) |
| `BTCPAY_BOT_EMAIL` | BTCPay login email |
| `BTCPAY_BOT_PASSWORD` | BTCPay login password |
| `BTCPAY_BASE_URL` | BTCPay Server URL (e.g. `https://pay.satflux.io`) |
| `APP_URL` | Panel base URL (used if `PANEL_URL` / `BTCPAY_BOT_PANEL_URL` unset) |
| `PANEL_URL` | Optional override for panel URL (first choice) |
| `BTCPAY_BOT_PANEL_URL` | Optional panel URL for the bot (second choice; common in Docker job docs) |

For local dev, **`APP_URL=http://localhost:8080`** (or your Vite host) is enough when the panel is reachable from the host. In production, set a URL the **bot host** can reach (often HTTPS), via `PANEL_URL`, `BTCPAY_BOT_PANEL_URL`, or `APP_URL`.

4. **Panel API token** – create via tinker in Docker:

```bash
docker compose exec -e XDG_CONFIG_HOME=/tmp php php artisan tinker
$u = \App\Models\User::where('email','bot@satflux.io')->first();
echo $u->createToken('btcpay-config-bot')->plainTextToken;
```

Copy the full token into `.env` or `.env.standalone` as `PANEL_BOT_TOKEN=1|xxx...`.

**403 on `/api/support/...`:** the Laravel user must have **`role` = `support` or `admin`** (BTCPay admin rights are unrelated). The token name must be exactly **`btcpay-config-bot`** (as in `createToken('btcpay-config-bot')`) so an unverified service account can still call support APIs; other tokens still require a verified email.

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

Ensure **`npm install`** has been run in `scripts/btcpay-config-bot` on that host. The bot reads `/path/to/satflux/.env` plus `.env.production` and **`.env.standalone`** at the project root—not only files inside Docker.

## Alternative: Laravel job (Docker)

If you prefer the Laravel job in Docker:

1. Set `BTCPAY_BOT_USE_JOB=true` in `.env`
2. Set `BTCPAY_BOT_PANEL_URL` so the container can reach the panel (e.g. `http://nginx:80` or `http://172.17.0.1:8080`)
3. Run queue worker: `docker compose exec php php artisan queue:work --queue=btcpay-config`

The poller on host is simpler and avoids Docker networking.

## Logging

- **stdout** – JSON lines
- **Log file** – `BTCPAY_BOT_LOG_FILE` or `/tmp/btcpay-config-bot.log`

If you redirect cron output to another file (e.g. `/tmp/btcpay-bot.log`), shell errors and bot JSON may appear there in addition to the logger file above.

## BTCPay UI assumptions

- No 2FA, no CAPTCHA
- **Blink**: Lightning setup page has "Use custom node" tab; bot fills `#ConnectionString` and clicks Save
- **Aqua (Boltz)**: Lightning setup page has "Configure Boltz" link; bot follows the wizard (Standalone → Continue → Import wallet → Enter core descriptor) and submits the descriptor in "Import Readonly L-BTC Wallet"
