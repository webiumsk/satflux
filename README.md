**Website:** [satflux.io](https://satflux.io)

# satflux.io - BTCPay control panel & business invoicing

Multi-tenant web app for merchants to run **BTCPay Server** stores and **Pro business invoicing** (companies, accounting documents, expenses) through a Vue SPA backed by Laravel and the **Greenfield API**.

**Current app version:** `2.0.0` (see `package.json`; `/api/version` reads the same value).

## Features

### BTCPay stores

- Store dashboard: payment invoices, wallets, Lightning addresses, pay button, PoS / Crowdfund apps, API keys
- Wallet types: **Blink**, **Aqua + Boltz** (descriptor / SamRock pairing), **Cashu** (mint + Lightning address, settlements UI)
- **Satoshi Tickets** (events, offline tickets, check-in) and **BTCPay Raffles** (incl. event bundle)
- CSV / XLSX exports, webhooks, optional **Stripe** plugin per store
- Post-setup **wallet verification checklist** per wallet type

### Accounts & plans

- Email/password, **LNURL-auth**, and **seed-first** onboarding (24-word recovery phrase)
- **Guest** demo: BTCPay store without Pro invoicing; upgrade to verified Free, then **Pro / Enterprise** via BTCPay subscription checkout
- One recovery phrase for account restore and (in local-first mode) invoicing sync across devices
- **Optional OFAC / sanctions screening** (off by default; enable via `COMPLIANCE_SCREENING_ENABLED` - see [docs/OFAC_COMPLIANCE.md](docs/OFAC_COMPLIANCE.md)) for operators who accept fiat (e.g. Stripe to bank account) and need geo/list checks

### Business invoicing (Pro+)

- Companies, contacts, issued documents: invoices, proformas, quotes, orders, delivery notes, credit notes, drafts, recurring profiles
- **Stock** items and **warehouses**; company branding and e-mail settings
- **Expenses** with attachments; browser Excel import; ISDOC extract and bulk PDF import via ephemeral server bridge
- **Bank payment matching** (CSV / CAMT.053) and optional b-mail inbound ([docs/BANK_PAYMENT_MATCHING.md](docs/BANK_PAYMENT_MATCHING.md))
- **SK e-faktura** (Peppol UBL, SAPI-SK send, inbound poll) for SK VAT payers ([docs/SK_EFAKTURA.md](docs/SK_EFAKTURA.md))
- **WooCommerce** plugin: connect shop, draft/issue documents, integration inbox ([docs/WOOCOMMERCE.md](docs/WOOCOMMERCE.md))
- Bitcoin pay links on issued invoices (BTCPay checkout); subscription billing can auto-issue paid invoices into a billing company

### Local-first invoicing (v2)

When `VITE_INVOICING_LOCAL_FIRST=true` and `INVOICING_LOCAL_FIRST=true`, core invoicing data lives in the browser (**Evolu** SQLite, E2EE). Optional relay sync via `VITE_EVOLU_RELAY_URL`. Server APIs remain for PDF render, e-mail, e-faktura, BTCPay bridges, and one-time **import from server** for legacy PostgreSQL data.

Rollout guide: **[docs/INVOICING_LOCAL_FIRST_ROLLOUT.md](docs/INVOICING_LOCAL_FIRST_ROLLOUT.md)**. Module overview: **[docs/BUSINESS_INVOICING.md](docs/BUSINESS_INVOICING.md)**.

## Tech stack

| Layer       | Stack                                                                                  |
| ----------- | -------------------------------------------------------------------------------------- |
| Backend     | Laravel 12, PHP 8.3+, Sanctum (SPA cookie auth), PostgreSQL, Redis                     |
| HTTP client | Laravel HTTP / Greenfield-style integration with BTCPay                                |
| Frontend    | Vue 3, TypeScript, Vite, Tailwind CSS, Pinia, Vue Router, vue-i18n (EN / SK / ES)      |
| Local-first | Evolu (`@evolu/vue`, `@evolu/web`) for invoicing SQLite + E2EE relay sync              |
| Hybrid UI   | Inertia.js (`@inertiajs/vue3`) where used alongside the SPA                            |
| Realtime    | Laravel Reverb (optional; see env / deployment)                                        |
| Dev / prod  | Docker Compose (`docker-compose.yml`) - nginx, php-fpm, postgres, redis                |

## Prerequisites

- Docker and Docker Compose
- Node.js **22+** and npm (frontend dev / build; see `package.json` `engines`)

## Local setup

1. **Clone the repository**

2. **Environment file**

   ```bash
   cp .env.example .env
   ```

3. **Configure `.env`** - database, `BTCPAY_BASE_URL`, `BTCPAY_API_KEY`, webhooks, invoicing flags, etc. For local SPA auth over HTTP:

   ```env
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,localhost:8080,127.0.0.1,127.0.0.1:8000,127.0.0.1:8080
   SESSION_DOMAIN=
   SESSION_SECURE_COOKIE=false
   SESSION_SAME_SITE=lax
   ```

   Local-first invoicing (optional): set `VITE_INVOICING_LOCAL_FIRST=true`, `INVOICING_LOCAL_FIRST=true`, and rebuild the frontend (`npm run build`).

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
| ESLint (with fix)         | `npm run lint` / `npm run lint:fix`                          |
| Frontend unit tests       | `npm run test`                                               |
| Semver bump               | `npm run version:patch` / `version:minor` / `version:major`  |
| PHPUnit                   | `docker-compose exec php php artisan test`                   |
| Single test               | `docker-compose exec php php artisan test --filter=TestName` |
| PHP style (Pint)          | `docker-compose exec php vendor/bin/pint`                    |

## Production deployment

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for the standalone (Caddy + Docker Compose) guide. Typical expectations:

- App behind a reverse proxy (e.g. Cloudflare) with `TrustProxies` and correct forwarded headers
- `APP_URL`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` aligned with your real host
- Optional: `LNURL_AUTH_DOMAIN` when LNURL-auth is enabled
- Local-first: set `VITE_*` flags at **build time** (`./deploy.sh`); runtime `INVOICING_LOCAL_FIRST` requires `php artisan optimize:clear`

## Architecture (short)

- **Browser** → Axios (`/api`, `withCredentials: true`, CSRF via Sanctum) → Laravel API (`routes/api.php`, `auth:sanctum`) → controllers → services.
- **Multi-tenancy:** each row in `stores` ties a local UUID (used in URLs) to `btcpay_store_id`. **`btcpay_store_id` must not be exposed to the frontend.**
- **Invoicing:** server-side PostgreSQL path (legacy) or **Evolu local-first** in the SPA; PRO gates the module; ephemeral Laravel bridges for PDF, compliance, and BTCPay.
- **API keys:** server-level key for provisioning vs per-merchant encrypted keys for scoped BTCPay calls (implemented in `app/Services/BtcPay` and related config).
- **Caching:** BTCPay responses may be cached; keys scoped by user / key hash where applicable.

More detail: **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

## Store creation (current flow)

1. **Step 1 - Basic info:** store name, default currency, timezone (and related fields). `wallet_type` is not required yet.
2. **Step 2 - Payment method:** choose **Blink**, **Aqua + Boltz** (descriptor and/or SamRock pairing flow), or **Cashu** (mint URL + Lightning address). Connection details are sent when applicable; Cashu is configured via the BTCPay Cashu plugin API.
3. **Provisioning:** BTCPay store is created via Greenfield; for Blink/Aqua, automation may run asynchronously (polling / status on the wallet connection screen).
4. After success, the user is sent to the **store dashboard** (e.g. setup wizard, PoS hints). Only the **local store UUID** appears in routes and JSON for the SPA.

## Wallet types & Cashu

- **Blink / Aqua (Boltz):** secrets live in `wallet_connections`; BTCPay is updated by your automation/support flows as designed.
- **Cashu:** no wallet-connection secret row; `wallet_type = cashu` and settings (mint, Lightning address) are stored in BTCPay via the Cashu plugin. The UI exposes **Cashu settlements** under the store (`?section=cashu`): rows from the plugin's payments API (settlement state, retry where supported).

## Wallet verification checklist

- Checklist **definitions** are per `wallet_type` (Blink, Aqua/Boltz, Cashu).
- Rows are **created lazily** (e.g. on first `GET /api/stores/{store}/checklist` or when `wallet_type` is first set) so older stores still get items.
- Items are **manual** checkboxes for the merchant (verify Lightning, test payment, Cashu mint/LN settings, etc.); they do not auto-sync from BTCPay.
- Copy in the UI is oriented toward **post-setup verification**, not "connect your wallet from scratch" only.

## Exports

- **CSV** - invoice exports with filters (status, dates); suitable for spreadsheets and accounting pipelines.
- **XLSX** - available where the product/plan allows (see app UI and subscription rules).
- Large exports may run asynchronously via jobs.

## LNURL-auth

When `LNURL_AUTH_ENABLED` is configured, the app can offer Lightning-based login / linking flows (challenge, status polling, linking to an existing account). Email/password and seed-first auth remain available. See routes under `/api/lnurl-auth/*` and `LnurlAuthController`.

## Security (summary)

- Store-scoped routes use middleware (e.g. **EnsureStoreOwnership**) so users cannot access another user's store by UUID guessing.
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
npm run test
```

Feature tests often **`Http::fake()`** BTCPay Greenfield responses - see existing store / wallet / Cashu tests for patterns.

## Documentation index

| Topic | Doc |
| ----- | --- |
| Deployment | [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) |
| Business invoicing | [docs/BUSINESS_INVOICING.md](docs/BUSINESS_INVOICING.md) |
| Local-first rollout | [docs/INVOICING_LOCAL_FIRST_ROLLOUT.md](docs/INVOICING_LOCAL_FIRST_ROLLOUT.md) |
| WooCommerce | [docs/WOOCOMMERCE.md](docs/WOOCOMMERCE.md) |
| Satoshi Tickets | [docs/SATOSHI_TICKETS.md](docs/SATOSHI_TICKETS.md) |
| Translations | [docs/TRANSLATION.md](docs/TRANSLATION.md) |

## License

MIT

## Contributors

Contributions are welcome: issues, pull requests, and translations. See **[docs/TRANSLATION.md](docs/TRANSLATION.md)** and the locale files in `resources/js/locales/` and `lang/`.
