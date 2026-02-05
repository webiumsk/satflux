# Connections branch – setup (push notifications + auto wallet config)

## 1. Bootstrap: register broadcast channels

So that `/broadcasting/auth` is available and channel authorization works, add the `channels` entry to `bootstrap/app.php` in `withRouting()`:

```php
->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    channels: __DIR__ . '/../routes/channels.php',  // add this line
    health: '/up',
)
```

## 2. Composer

```bash
composer update
```

This installs `laravel/reverb` (already added to `composer.json`).

## 3. NPM (Echo + Pusher JS)

```bash
npm install
```

Adds `laravel-echo` and `pusher-js` for in-app push notifications.

## 4. Environment (optional – for push notifications)

To enable real-time push to support/admin:

- Set `BROADCAST_CONNECTION=reverb` in `.env` (or `.env.production`).
- Generate Reverb credentials (any unique strings; server and client must match):

```bash
# Option A: let Laravel generate (run in app container or locally)
php artisan reverb:install

# Option B: generate manually
php -r "echo 'REVERB_APP_ID=' . substr(bin2hex(random_bytes(8)), 0, 16) . PHP_EOL;"
php -r "echo 'REVERB_APP_KEY=' . base64_encode(random_bytes(24)) . PHP_EOL;"
php -r "echo 'REVERB_APP_SECRET=' . base64_encode(random_bytes(24)) . PHP_EOL;"
```

- **Local (no Docker)** – add to `.env` and run Reverb manually:

```env
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
VITE_APP_URL="${APP_URL}"
```

Run Reverb when developing: `php artisan reverb:start`

- **Local (Docker – docker-compose.yml)** – Reverb runs in its own container; Nginx proxies `/app` and `/apps`. In `.env` (used by both PHP and Reverb containers):

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
# Laravel (server) sends to reverb container
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend (browser) connects to localhost:8080
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
VITE_APP_URL="${APP_URL:-http://localhost:8080}"
```

`REVERB_HOST=reverb` is for Laravel (server-side) so it pushes to the Reverb container. The browser uses `localhost:8080` (VITE_*), so the built JS connects to `ws://localhost:8080/app`. Start stack with `docker compose up -d` (reverb starts automatically).

- **Production (Docker)** – Reverb runs in its own container; Nginx proxies `/app` and `/apps` to it. In `.env.production`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
# Laravel (server) sends to reverb container
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# VITE_* are baked into the frontend build – browser connects to public URL
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=satflux.io
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
VITE_APP_URL="${APP_URL}"
```

Laravel (php container) pushes to `reverb:8080`. The built frontend connects to `wss://satflux.io` (port 443). Run `npm run build` with these env vars.

If you leave `BROADCAST_CONNECTION=null` or omit the VITE_* vars, support/admin still get **email** and the **Support** badge; only the in-app toast is skipped.

## 5. Auto wallet connection config (scheduled command)

The command `wallet-connections:attempt-config` is scheduled every 15 minutes (see `routes/console.php`). It:

- Selects wallet connections with status `needs_support`
- For each, uses the store owner’s BTCPay API key and tries `LightningService::connectLightningNode`
- On success, marks the connection as connected (merchant is notified)

Manual run:

```bash
php artisan wallet-connections:attempt-config
php artisan wallet-connections:attempt-config --limit=5
```

Ensure the scheduler is running (e.g. cron: `* * * * * php /path/to/artisan schedule:run`).
