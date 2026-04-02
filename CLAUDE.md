# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**satflux.io** - a multi-tenant BTCPay Server control panel. Users manage their BTCPay stores (invoices, wallets, Lightning, exports) through this SPA backed by Laravel's Greenfield API integration.

## Development Commands

### Frontend

```bash
npm run dev          # Vite dev server (watch mode)
npm run build        # Production build
npm run lint         # ESLint with auto-fix
```

### Backend (all via Docker)

```bash
docker compose up -d                              # Start all services
docker compose exec php php artisan migrate       # Run migrations
docker compose exec php php artisan test          # Run all PHPUnit tests
docker compose exec php php artisan test --filter=TestName  # Run single test
docker compose exec php php artisan optimize:clear  # Clear all caches (required after .env changes)
docker compose exec php php artisan tinker        # Interactive shell
docker compose exec php composer install          # Install PHP deps
```

If Git, ESLint, or exports fail with permission errors on `storage/app/private` or `storage/app/exports` (often `www-data` from Docker), fix ownership on the host, e.g. `sudo chown -R "$USER:$USER" storage/app/private storage/app/exports`. Export files are gitignored (`*.csv`, `*.xlsx`).

### Versioning

```bash
npm run version:patch   # Bump patch version
npm run version:minor   # Bump minor version
```

## Architecture

### Request Flow

```
Browser (Vue SPA)
  → Axios (/api, withCredentials: true)
  → Laravel API routes (routes/api.php, all guarded by auth:sanctum)
  → Controllers → Services
    ├─ Eloquent (PostgreSQL)
    └─ BtcPayClient → BTCPay Server Greenfield API
```

### Frontend (`resources/js/`)

- **`app.ts`**: Vue app bootstrap; detects Inertia vs. SPA mode via `data-page` attribute
- **`bootstrap.ts`**: Fetches `/sanctum/csrf-cookie` on boot; sets up Axios with `X-XSRF-TOKEN` header
- **`router/`**: Vue Router with navigation guards - checks `authStore.user` to enforce auth; 401s redirect to login
- **`store/`**: Pinia stores (Composition API style). Key stores: `auth`, `stores` (BTCPay stores + dashboard), `flash`, `onboarding`
- **`pages/`**: Route-level components (auth, stores, admin, etc.)
- **`components/`**: Reusable UI components
- **`services/api.ts`**: Axios instance; removes `Content-Type` for FormData, adds CSRF header
- **`locales/`**: Vue-i18n translation files (multi-language)

### Backend (`app/`)

- **`Http/Controllers/`**: Thin controllers delegating to services
- **`Services/BtcPay/`**: 13 service classes wrapping BTCPay Greenfield API (BtcPayClient is the HTTP client)
- **`Http/Middleware/EnsureStoreOwnership`**: Enforces multi-tenant isolation - validates `user_id` on every store-scoped request
- **`Jobs/`**: Async jobs for exports and webhook processing

### Multi-Tenancy & Security

- Store access is enforced at the application layer via the `stores` table (`user_id` → `btcpay_store_id`)
- Only local UUIDs are ever sent to the frontend - `btcpay_store_id` is never exposed
- Dual API key architecture: server-level key (provisioning) vs. per-merchant encrypted keys (scoped operations)
- Audit logging on sensitive actions (store create/update, exports)

### Authentication

- Laravel Sanctum SPA cookie-based auth (not tokens)
- Browser sends `laravel_session` cookie + `X-XSRF-TOKEN` header on every request
- For local dev: `SESSION_SECURE_COOKIE=false`, `SANCTUM_STATEFUL_DOMAINS=localhost:8080`

## Key Conventions

- **Never expose `btcpay_store_id`** to the frontend; always use the local UUID
- **`EnsureStoreOwnership` middleware** must be applied to all store-scoped API routes
- BTCPay API responses are cached with cache keys scoped by user/API key hash
- Webhook signature verification is active when `BTCPAY_WEBHOOK_SECRET` is set
- Test BTCPay HTTP calls using `Http::fake()` (see existing store creation tests for pattern)
- `php artisan optimize:clear` is required after any `.env` or config file changes
