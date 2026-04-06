---
name: satflux
description: >-
  Multi-tenant BTCPay Server control panel (Laravel + Vue SPA, Sanctum, Greenfield API).
  Use when working in the satflux repository, BTCPay integration, store-scoped APIs,
  invoices/Lightning/exports, or when the user mentions satflux.io.
---

# Satflux

## Purpose

**satflux.io** — merchants manage BTCPay stores (invoices, wallets, Lightning, exports) through a Vue SPA; Laravel talks to BTCPay via the Greenfield API. Multi-tenancy is enforced in app code, not only in BTCPay.

For full command and file layout detail, read [CLAUDE.md](../../../CLAUDE.md) at the repo root.

## Commands

| Area | Command |
|------|---------|
| Frontend | `npm run dev`, `npm run build`, `npm run lint` |
| Backend | Via Docker: `docker compose exec php php artisan …` (migrate, test, optimize:clear, tinker) |
| After `.env` / config changes | `docker compose exec php php artisan optimize:clear` |

If `storage/app/private` or `storage/app/exports` has permission issues (often `www-data` from Docker): `sudo chown -R "$USER:$USER" storage/app/private storage/app/exports`.

## Request flow

Browser → Axios `/api` (`withCredentials: true`, CSRF via `bootstrap.ts`) → `routes/api.php` (`auth:sanctum`) → controllers → services → PostgreSQL and/or `BtcPayClient`.

## Frontend (`resources/js/`)

- Entry: `app.ts`, `bootstrap.ts`
- Router guards: `router/` (auth via Pinia `authStore`)
- State: `store/` — notably `auth`, `stores`, `flash`, `onboarding`
- API client: `services/api.ts`
- User-visible copy: `locales/*.json` (Vue i18n) — add/change keys when changing UI strings

## Backend (`app/`)

- HTTP: thin `Http/Controllers/`, logic in services
- BTCPay: `Services/BtcPay/` (`BtcPayClient` = HTTP to Greenfield)
- Async: `Jobs/` (exports, webhooks)
- **Every store-scoped API route** must use `EnsureStoreOwnership` (or equivalent isolation)

## Non-negotiables

1. **Never expose `btcpay_store_id` to the frontend** — only the local store UUID from the `stores` table.
2. **Store isolation** — verify ownership on every store-scoped operation; middleware must stay applied to new routes.
3. **Sanctum SPA** — cookie session + `X-XSRF-TOKEN`; not bearer tokens for the browser app.
4. **BTCPay HTTP in tests** — use `Http::fake()`; follow patterns in existing store-creation tests.
5. **Webhooks** — signature verification when `BTCPAY_WEBHOOK_SECRET` is set.

## Local dev auth hints

`SESSION_SECURE_COOKIE=false`, `SANCTUM_STATEFUL_DOMAINS` must include the dev origin (e.g. `localhost:8080`).

## When changing code

- Match existing patterns in the same layer (Pinia style, controller thinness, BtcPay service usage).
- New user-facing strings: update **all** relevant locale files, not only English.
- After substantive backend changes, run targeted PHPUnit: `docker compose exec php php artisan test --filter=…`
