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

`npm install` pulls Playwright’s Chromium build, but **Chromium still needs host OS libraries**. On a minimal VPS/Docker-host image, the browser may fail immediately with errors like **`libnspr4.so: cannot open shared object file`** (exit code 127).

### Why the first Blink save can “work” but a later change hits the bot

After you save a Blink connection, **`WalletConnectionService`** often tries **`connectLightningNode`** over the BTCPay Greenfield API first (when the merchant has a BTCPay API key and the connection is new `pending` Blink). If that succeeds, Laravel marks the wallet **`connected`** and **Playwright never runs** — it can look like “the bot configured it” even though only the API ran.

When you **change** an existing Lightning setup (another Blink string, or switching from another wallet type), that API path may **not replace** the live node or may return an error. The row stays **`pending`** with **`reconfig`** set where applicable, and the **poller must open Chromium**. If the host is missing `libnspr4` (etc.), you only notice the failure on those runs.

So: fix **OS dependencies** above for any host that runs `poll.js`; do not assume the first success implied a working Playwright stack.

Install OS libraries for Chromium (Debian/Ubuntu, as root). **Use Playwright’s installer first**—it tracks Chromium’s requirements and distro-specific package names (e.g. Ubuntu 24.04 `libasound2t64`) better than a hand-maintained `apt` list:

```bash
cd /path/to/satflux/scripts/btcpay-config-bot
npx playwright install-deps chromium
```

See [Playwright: system dependencies](https://playwright.dev/docs/cli#install-system-dependencies) for OS notes.

If you **cannot** run `install-deps` (air‑gapped host, policy, or the command fails) and must use `apt` only, install the set that matches **your** Ubuntu/Debian release. Package names differ between releases—for example **Ubuntu 24.04+** often provides ALSA as `libasound2t64` instead of `libasound2`. Cross-check the [Playwright `install_deps_linux` script](https://github.com/microsoft/playwright/blob/main/packages/playwright-core/bin/install_deps_linux.sh) (or run `install-deps` on a similar online host and read the suggested `apt-get` line) for the full set. A typical baseline (adjust `libasound2` vs `libasound2t64` per release) is:

```bash
sudo apt-get update
sudo apt-get install -y libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
  libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 \
  libasound2t64
```

On **22.04 LTS**, use `libasound2` instead of `libasound2t64` if the `t64` package is not available.

Then re-run a one-shot poll and confirm the log passes **`panel_reveal`** without a **`browserType.launch`** / **`shared libraries`** error.

Cron must run **`npm install` / `npx playwright install chromium`** as the **same user** that runs `poll.js` (e.g. root uses `/root/.cache/ms-playwright`); switching users without reinstalling browsers can also produce confusing failures.

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
