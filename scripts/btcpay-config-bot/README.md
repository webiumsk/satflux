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

3. **Environment** – add to main `.env`:

| Variable | Description |
|----------|-------------|
| `PANEL_BOT_TOKEN` | Sanctum token for bot (support/admin user) |
| `PANEL_BOT_PASSWORD` | Bot user password |
| `BTCPAY_BOT_EMAIL` | BTCPay login email |
| `BTCPAY_BOT_PASSWORD` | BTCPay login password |
| `BTCPAY_BASE_URL` | BTCPay Server URL (e.g. `https://pay.dvadsatjeden.org`) |
| `APP_URL` | Panel URL – **use `http://localhost:8080`** when panel runs locally (host can reach localhost) |

4. **Panel API token** – create via tinker in Docker:

```bash
docker compose exec -e XDG_CONFIG_HOME=/tmp php php artisan tinker
$u = \App\Models\User::where('email','bot@satflux.io')->first();
echo $u->createToken('btcpay-config-bot')->plainTextToken;
```

Copy the full token into `.env` as `PANEL_BOT_TOKEN=1|xxx...`.

### Run poller (continuous)

```bash
cd scripts/btcpay-config-bot
npm run poll
```

Polls every 2 minutes for `needs_support` connections and configures each.

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

```cron
*/2 * * * * cd /path/to/D21Panel/scripts/btcpay-config-bot && node poll.js --once
```

## Alternative: Laravel job (Docker)

If you prefer the Laravel job in Docker:

1. Set `BTCPAY_BOT_USE_JOB=true` in `.env`
2. Set `BTCPAY_BOT_PANEL_URL` so the container can reach the panel (e.g. `http://nginx:80` or `http://172.17.0.1:8080`)
3. Run queue worker: `docker compose exec php php artisan queue:work --queue=btcpay-config`

The poller on host is simpler and avoids Docker networking.

## Logging

- **stdout** – JSON lines
- **Log file** – `BTCPAY_BOT_LOG_FILE` or `/tmp/btcpay-config-bot.log`

## BTCPay UI assumptions

- No 2FA, no CAPTCHA
- **Blink**: Lightning setup page has "Use custom node" tab; bot fills `#ConnectionString` and clicks Save
- **Aqua (Boltz)**: Lightning setup page has "Configure Boltz" link; bot follows the wizard (Standalone → Continue → Import wallet → Enter core descriptor) and submits the descriptor in "Import Readonly L-BTC Wallet"
