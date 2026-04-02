**Website:** [satflux.io](https://satflux.io)

# satflux.io - BTCPay Server control panel

Multi-tenant web app for merchants to manage **BTCPay Server** stores (invoices, wallets, Lightning, PoS apps, exports, and more) through a Vue SPA backed by Laravel and the **Greenfield API**.

**Current app version:** see `package.json` (`version` field). The `/api/version` endpoint reads the same value.

## Tech stack

| Layer       | Stack                                                                                  |
| ----------- | -------------------------------------------------------------------------------------- |
| Backend     | Laravel 12, PHP 8.3+, Sanctum (SPA cookie auth), PostgreSQL, Redis                     |
| HTTP client | Laravel HTTP / Greenfield-style integration with BTCPay                                |
| Frontend    | Vue 3, TypeScript, Vite, Tailwind CSS, Pinia, Vue Router, vue-i18n (e.g. EN / SK / ES) |
| Hybrid UI   | Inertia.js (`@inertiajs/vue3`) where used alongside the SPA                            |
| Realtime    | Laravel Reverb (optional; see env / deployment)                                        |
| Dev / prod  | Docker Compose (`docker-compose.yml`) - nginx, php-fpm, postgres, redis                |

## Prerequisites

- Docker and Docker Compose
- Node.js 18+ and npm (frontend dev / build)

## Local setup

1. **Clone the repository**

2. **Environment file**

   ```bash
   cp .env.example .env
   ```

3. **Configure `.env`** - database, `BTCPAY_BASE_URL`, `BTCPAY_API_KEY`, webhooks, etc. For local SPA auth over HTTP:

   ```env
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,127.0.0.1,127.0.0.1:8080
   SESSION_DOMAIN=
   SESSION_SECURE_COOKIE=false
   SESSION_SAME_SITE=lax
   ```

4. **Start containers**

   ```bash
   docker-compose up -d
   ```

5. **PHP dependencies**

   ```bash
   docker-compose exec php composer install
   ```

6. **Application key**

   ```bash
   docker-compose exec php php artisan key:generate
   ```

7. **Migrations**

   ```bash
   docker-compose exec php php artisan migrate
   ```

8. **Node dependencies**

   ```bash
   npm install
   ```

9. **Frontend**
   - Development (Vite HMR): `npm run dev`
   - Production assets: `npm run build`

10. **After changing `.env` or config**

    ```bash
    docker-compose exec php php artisan optimize:clear
    ```

## Development URLs (typical Docker setup)

- App / API base: `http://localhost:8080`
- API prefix: `/api`
- PostgreSQL: `localhost:5432` (per compose mapping)
- Redis: `localhost:6379` (per compose mapping)

## Common commands

| Task                      | Command                                                      |
| ------------------------- | ------------------------------------------------------------ |
| Frontend dev server       | `npm run dev`                                                |
| Frontend production build | `npm run build`                                              |
| ESLint (with fix)         | `npm run lint`                                               |
| Semver bump               | `npm run version:patch` / `version:minor` / `version:major`  |
| PHPUnit                   | `docker-compose exec php php artisan test`                   |
| Single test               | `docker-compose exec php php artisan test --filter=TestName` |
| PHP style (Pint)          | `docker-compose exec php vendor/bin/pint`                    |

## Production deployment

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for the standalone (Caddy + Docker Compose) guide. Typical expectations:

- App behind a reverse proxy (e.g. Cloudflare) with `TrustProxies` and correct forwarded headers
- `APP_URL`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` aligned with your real host
- Optional: `LNURL_AUTH_DOMAIN` when LNURL-auth is enabled

## Architecture (short)

- **Browser** → Axios (`/api`, `withCredentials: true`, CSRF via Sanctum) → Laravel API (`routes/api.php`, `auth:sanctum`) → controllers → services.
- **Multi-tenancy:** each row in `stores` ties a local UUID (used in URLs) to `btcpay_store_id`. **`btcpay_store_id` must not be exposed to the frontend.**
- **API keys:** server-level key for provisioning vs per-merchant encrypted keys for scoped BTCPay calls (implemented in `app/Services/BtcPay` and related config).
- **Caching:** BTCPay responses may be cached; keys scoped by user / key hash where applicable.

## Store creation (current flow)

1. **Step 1 - Basic info:** store name, default currency, timezone (and related fields). `wallet_type` is not required yet.
2. **Step 2 - Payment method:** choose **Blink**, **Aqua + Boltz** (descriptor and/or SamRock pairing flow), or **Cashu** (mint URL + Lightning address). Connection details are sent when applicable; Cashu is configured via the BTCPay Cashu plugin API.
3. **Provisioning:** BTCPay store is created via Greenfield; for Blink/Aqua, automation may run asynchronously (polling / status on the wallet connection screen).
4. After success, the user is sent to the **store dashboard** (e.g. setup wizard, PoS hints). Only the **local store UUID** appears in routes and JSON for the SPA.

## Wallet types & Cashu

- **Blink / Aqua (Boltz):** secrets live in `wallet_connections`; BTCPay is updated by your automation/support flows as designed.
- **Cashu:** no wallet-connection secret row; `wallet_type = cashu` and settings (mint, Lightning address) are stored in BTCPay via the Cashu plugin. The UI exposes **Cashu settlements** under the store (`?section=cashu`): rows from the plugin’s payments API (settlement state, retry where supported).

## Wallet verification checklist

- Checklist **definitions** are per `wallet_type` (Blink, Aqua/Boltz, Cashu).
- Rows are **created lazily** (e.g. on first `GET /api/stores/{store}/checklist` or when `wallet_type` is first set) so older stores still get items.
- Items are **manual** checkboxes for the merchant (verify Lightning, test payment, Cashu mint/LN settings, etc.); they do not auto-sync from BTCPay.
- Copy in the UI is oriented toward **post-setup verification**, not “connect your wallet from scratch” only.

## Exports

- **CSV** - invoice exports with filters (status, dates); suitable for spreadsheets and accounting pipelines.
- **XLSX** - available where the product/plan allows (see app UI and subscription rules).
- Large exports may run asynchronously via jobs.

## LNURL-auth

When `LNURL_AUTH_ENABLED` is configured, the app can offer Lightning-based login / linking flows (challenge, status polling, linking to an existing account). Email/password auth remains available. See routes under `/api/lnurl-auth/*` and `LnurlAuthController`.

## Security (summary)

- Store-scoped routes use middleware (e.g. **EnsureStoreOwnership**) so users cannot access another user’s store by UUID guessing.
- Sanctum SPA session + CSRF for browser clients.
- Audit logging on sensitive actions (e.g. store changes, exports) where implemented.
- Webhook signature verification when `BTCPAY_WEBHOOK_SECRET` is set.
- Rate limiting on authentication-related endpoints.

**Reporting vulnerabilities:** do not use public issues for undisclosed problems. See **[SECURITY.md](SECURITY.md)** for contact, scope, and (on GitHub) private vulnerability reporting.

**Dependencies:** [`.github/dependabot.yml`](.github/dependabot.yml) configures Dependabot version updates; in the GitHub repo enable **Dependabot alerts** and **Dependabot security updates** under _Settings → Code security and analysis_.

## BTCPay API key (server)

The server-level **`BTCPAY_API_KEY`** should be limited to what provisioning and global jobs need (store lifecycle, admin assignments, webhooks, etc.). Per-merchant operations use **merchant API keys** stored encrypted and never sent to the browser.

## Testing

```bash
docker-compose exec php php artisan test
```

Feature tests often **`Http::fake()`** BTCPay Greenfield responses - see existing store / wallet / Cashu tests for patterns.

## License

MIT

## Contributors

Contributions are welcome: issues, pull requests, and translations. See **[docs/TRANSLATION.md](docs/TRANSLATION.md)** and the locale files in `resources/js/locales/` and `lang/`.
