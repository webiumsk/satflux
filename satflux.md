# satflux.io — Analýza projektu

## 1. Prehľad projektu

**satflux.io** je multi-tenant Bitcoin/BTCPay Server control panel — SPA webová aplikácia, ktorá umožňuje používateľom spravovať ich BTCPay obchody (invoices, peňaženky, Lightning, exporty, štatistiky). Aplikácia je postavená na modeli predplatného (Free / Pro / Enterprise).

**Aktuálna verzia:** 1.0.97 (beta)

---

## 2. Technologický stack

### Backend
- **Laravel 12** (PHP 8.3)
- **PostgreSQL** — primárna databáza
- **Redis** — cache, sessions, queue
- **Laravel Sanctum** — SPA cookie-based autentifikácia
- **Laravel Reverb** (voliteľný) — real-time WebSockets

### Frontend
- **Vue 3 + TypeScript**
- **Vite** — bundling a hot module replacement
- **Vue Router** — client-side routing s auth guards
- **Pinia** — state management (Composition API)
- **TailwindCSS** — styling
- **Axios** — HTTP klient
- **Vue-i18n** — multi-language podpora

### Integrácie
- **BTCPay Server** — Greenfield API
- **Stripe** (voliteľný, Pro+) — platobné brány
- **Pusher/Reverb** (voliteľný) — real-time notifikácie

### Infraštruktúra
- **Docker Compose** — lokálny vývoj (nginx, php-fpm, postgres, redis)
- **Caddy** — produkčný deployment
- Cloudflare-compatible proxy hlavičky

---

## 3. Architektúra a tok požiadaviek

```
Browser (Vue SPA)
  ↓
Axios (/api, withCredentials: true, X-XSRF-TOKEN)
  ↓
Laravel API Routes (routes/api.php)
  ↓
HTTP Middleware Pipeline
  ├─ auth:sanctum                  — overenie session cookie
  ├─ EnsureStoreOwnership          — multi-tenant izolácia
  ├─ EnsureAdminRole / EnsureSupportRole — role-based access
  ├─ EnsurePlanAllows*             — subscription feature gates
  └─ AuditLog                      — záznam citlivých akcií
  ↓
Controllers → Services
  ├─ BtcPay/* services             — HTTP wrapper nad Greenfield API
  ├─ Application services          — doménová logika
  └─ Eloquent ORM                  — PostgreSQL operácie
  ↓
Dátové zdroje
  ├─ PostgreSQL    — lokálne dáta (users, stores, subscriptions, exports...)
  ├─ BTCPay Server — invoices, wallets, Lightning, apps
  └─ Redis         — cache, sessions, job queue
```

---

## 4. Databázová schéma (kľúčové modely)

| Model | Účel | Kľúčové polia |
|-------|------|---------------|
| **User** | Auth + subscripcia | id, email, password, role, btcpay_api_key (šifrované), subscription_expires_at, lightning_public_key, nostr_public_key |
| **Store** | BTCPay store metadata | id (UUID), user_id, btcpay_store_id, name, default_currency, timezone, wallet_type, webhook_secret |
| **SubscriptionPlan** | Cenové úrovne | code (free/pro/enterprise), max_stores, max_api_keys, max_ln_addresses, max_events, features (JSON) |
| **Subscription** | Sledovanie predplatného | user_id, plan_id, status (active/grace/expired), expires_at, auto_renew |
| **Export** | Exporty invoices | store_id, user_id, format (standard/accounting), source (manual/automatic), status, file_path, signed_url |
| **WalletConnection** | Tajomstvá peňaženiek | store_id, type, encrypted_secret, status, bot_failure_message, revealed_last_at |
| **StoreChecklist** | Onboarding kroky | store_id, item_key, completed |
| **StoreApiKey** | Store-level API tokeny | store_id, key_hash, display_name, secret (hashed) |
| **UserApiKey** | User-level API tokeny | user_id, key_hash, name, secret (hashed) |
| **App** | BTCPay App inštancie | store_id, type (pos/plugin/crowdfund/tickets), btcpay_app_id |
| **PosTerminal / PosOrder** | Point-of-Sale | store_id, name, sledovanie objednávok |
| **AuditLog** | Auditná stopa | user_id, action, model_type, model_id, changes (JSON) |

---

## 5. Autentifikácia a autorizácia

### Metódy prihlásenia
1. **Email + heslo** — štandardná registrácia/login
2. **LNURL-Auth** (LUD-04) — QR kód login pre Lightning peňaženky
3. **Nostr** (NIP-98) — overenie Nostr event podpisu
4. **Remember Me** — predĺžená session

### Autorizačný model (role)

| Rola | Stores | LN adresy | API kľúče | Ticket udalosti | Funkcie |
|------|--------|-----------|-----------|-----------------|---------|
| Free | 1 | 2 | 1 | 1 | Základné |
| Pro | 3 | neob. | 3 | 3 | Stripe, auto-export, pokročilé štatistiky |
| Enterprise | neob. | neob. | neob. | neob. | Webhooks, vlastný reporting |
| Admin/Support | neob. | neob. | neob. | neob. | + správa používateľov |

### Auth tok (registrácia)
```
1. POST /api/auth/register → validácia, vytvorenie User, odoslanie verifikačného emailu
2. GET /auth/verify-email/{id}/{hash} → verifikácia, sync s BTCPay Serverom
3. POST /api/auth/login → session cookie + CSRF token
4. Všetky /api požiadavky: laravel_session cookie + X-XSRF-TOKEN header
```

---

## 6. API Routes architektúra

### Verejné endpoints (bez auth)
- `GET /health` — health check
- `POST /auth/register` / `POST /auth/login`
- `GET /auth/verify-email/{id}/{hash}`
- `GET /lnurl-auth/*` / `GET /nostr-auth/*`
- `POST /webhooks/btcpay` — BTCPay webhooks (HMAC-SHA256 overenie)
- `GET /pricing`, `GET /plan-features`, `GET /version`

### Autentifikované endpoints (auth:sanctum)
- **Používateľ**: `/user`, `/user/limits`, `/messages`
- **Stores**: CRUD, logo upload, nastavenia, checklist, dashboard
- **Invoices**: zoznam, export CSV
- **Exports**: vytvoriť, stiahnuť, retry, automatické reporty
- **Apps**: PoS, plugin, crowdfund, tickets
- **Lightning Addresses**: CRUD
- **Stripe**: nastavenia, webhook registrácia
- **API Keys**: user-level aj store-level CRUD
- **Wallet Connections**: vytvorenie, odhalenie, test, konfigurácia
- **PoS Terminals & Orders**
- **Tickets**: udalosti, typy lístkov, objednávky, check-in
- **Subscriptions**: checkout, stav, kredity

### Admin routes (`/admin/*`)
- Správa používateľov (zoznam, detail, update, delete)
- Štatistiky (real-time, export)

### Support routes (`/support/*`)
- Správa wallet connections
- Sledovanie user connections
- Prístup k BTCPay store URL

---

## 7. Frontend štruktúra

### Vstupný bod: `resources/js/`

```
app.ts          — Vue bootstrap, detekuje Inertia vs. SPA mód
bootstrap.ts    — CSRF cookie fetch, Axios setup
router/         — Vue Router + auth guards (401 → login redirect)
store/          — Pinia stores
  ├─ auth.ts          — User, login/logout, fetchUser
  ├─ stores.ts        — BTCPay stores, currentStore
  ├─ flash.ts         — Toast notifikácie
  ├─ apps.ts          — Správa Apps
  ├─ onboarding.ts    — Wizard pre vytvorenie store
  └─ tickets.ts       — Ticket events & orders
services/
  └─ api.ts     — Axios inštancia s interceptormi
locales/        — Vue-i18n prekladové súbory
pages/          — Route-level komponenty
components/     — Zdieľané UI komponenty
```

### Stránky (pages/)
```
Landing.vue                 — Verejná landing page
auth/                       — Login, Register, PasswordReset, LNURL, Nostr
Dashboard.vue               — Hlavný dashboard
Pricing.vue                 — Výber plánu
account/                    — Profil, API Keys, Subscription
stores/
  ├─ Index / Create / Show
  ├─ Invoices, Exports, Reports
  ├─ Apps, LightningAddresses, Stripe
  ├─ Settings, WalletConnection
  ├─ PosTerminals
  └─ Tickets/ (Events, TicketTypes, Orders)
admin/                      — Users, Stats, Documentation, FAQ
```

---

## 8. Backend services vrstva

### BtcPay/* (wrappery Greenfield API)

| Service | Zodpovednosť |
|---------|--------------|
| BtcPayClient | HTTP klient s retry logikou, rate limit handling, dual API key |
| StoreService | CRUD operácie stores |
| InvoiceService | Zoznam, vyhľadávanie, detail invoices |
| LightningService | Lightning node create/list/activate |
| LightningAddressService | Správa Lightning adries |
| UserService | Get/create BTCPay users, email lookup |
| AppService | Správa Apps (PoS, plugin, crowdfund, tickets) |
| TicketService | Udalosti, lístky, objednávky, check-in |
| WebhookService | Webhook správa a overenie |
| StripeService | Stripe integrácia |
| MerchantApiKeyService / StoreApiKeyService | Správa API kľúčov |

**BtcPayClient kľúčové funkcie:**
- Dual API key: server-level (provisioning) vs. user-level (scoped operations)
- Retry s exponenciálnym backoff (max 3 pokusy)
- Rate limit handling (429 + Retry-After hlavička)
- Cache s izoláciou per-user (API key hash v cache kľúči)
- Multipart upload s bezpečným spracovaním názvov súborov

### Aplikačné services
- **StoreChecklistService** — dynamický checklist podľa wallet_type
- **StatsService** — štatistiky stores (pokročilé len Pro+)
- **SubscriptionService** — validácia a priradenie plánov
- **WalletConnectionService/Validator** — správa wallet pripojení
- **InvoiceSourceService** — detekcia zdroja invoices

---

## 9. Asynchrónne jobs (queue)

| Job | Queue | Účel | Timeout |
|-----|-------|------|---------|
| GenerateCsvExport | exports | Streaming CSV generácia (stránkované invoices) | 600s |
| GenerateXlsxExport | exports | XLSX export (PHPOffice) | 600s |
| ProcessMonthlyExports | default | Automatické mesačné exporty (Pro+) | — |
| ProcessBtcPayWebhook | webhooks | Asynchrónne spracovanie webhookov | — |

---

## 10. Export systém

### Formáty

**Standard CSV:**
`invoiceId, createdTime, status, amount, currency, paidAmount, paidSats, paymentRate, paymentMethod, buyerEmail, orderId, checkoutLink`

**Accounting CSV:**
`invoice_id, issue_date, settlement_date, gross_amount, currency, payment_method, status, external_reference`

### Funkcie
- Streaming generácia pre veľké datasety
- Filtrovanie podľa dátumu a statusu
- Automatické mesačné exporty (Pro+)
- Signed URL s konfigurovateľným TTL (default 3600s)
- Sledovanie zdroja: `SOURCE_MANUAL` vs `SOURCE_AUTOMATIC`

---

## 11. Subscription a cenový systém

### Plány

| Funkcia | Free | Pro | Enterprise |
|---------|------|-----|------------|
| Max Stores | 1 | 3 | Neobmedzené |
| LN adresy | 2 | neob. | neob. |
| API kľúče | 1 | 3 | neob. |
| Ticket udalosti | 1 | 3 | neob. |
| Stripe | ✗ | ✓ | ✓ |
| Auto CSV exporty | ✗ | ✓ | ✓ |
| Pokročilé štatistiky | ✗ | ✓ | ✓ |
| Custom branding | ✗ | ✓ | ✓ |
| Webhooks | ✗ | ✗ | ✓ |

### Subscription lifecycle
```
active → grace (14 dní po expirácii) → expired
```

### Vynucovanie limitov
- Middleware: `EnsurePlanAllowsExportsAccess`, `EnsurePlanAllowsLnAddressCreation` atď.
- Model method: `$user->planFeature('advanced_statistics')` → boolean
- Cache s user ID pre izoláciu

---

## 12. Multi-tenancy a bezpečnosť

### Izolačné mechanizmy

1. **EnsureStoreOwnership middleware** — každý store-scoped request overí `store.user_id === auth.id`
2. **UUID-based routing** — frontend nikdy nevidí `btcpay_store_id`, len lokálne UUID
3. **Šifrovanie API kľúčov** — user aj store API kľúče šifrované v databáze
4. **Cache izolácia** — kľúč obsahuje `md5($userApiKey)` (zabraňuje cross-tenant pollution)
5. **Webhook overenie** — HMAC-SHA256 podpis pri nastavenej premennej `BTCPAY_WEBHOOK_SECRET`
6. **Audit logging** — citlivé operácie logované (store.created, export.created, api-key.created)
7. **Rate limiting** — auth endpoints throttled (6 req/min pre email verifikáciu)
8. **Session bezpečnosť** — HttpOnly, Secure (prod), SameSite=Lax

### Kľúčové pravidlá
- Nikdy neodhaluj `btcpay_store_id` frontendu
- `EnsureStoreOwnership` musí byť na všetkých store-scoped routách
- Dual API key architektúra: server-level (provisioning) vs. per-merchant (operácie)

---

## 13. Lokalizácia

- **Frontend**: Vue-i18n, súbory v `resources/js/locales/`
- **Backend**: locale uložená v session, endpoint `/api/locale`
- Formátovanie dátumov, čísel a mien podľa locale

---

## 14. Dôležité konfiguračné premenné

```env
APP_URL, APP_ENV, APP_DEBUG, APP_TIMEZONE
DB_CONNECTION=pgsql, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
REDIS_HOST, REDIS_PORT
SESSION_DRIVER=database, SESSION_DOMAIN, SESSION_SECURE_COOKIE
SANCTUM_STATEFUL_DOMAINS
BTCPAY_BASE_URL, BTCPAY_API_KEY, BTCPAY_WEBHOOK_SECRET
LNURL_AUTH_ENABLED, LNURL_AUTH_DOMAIN
EXPORT_SIGNED_URL_TTL (default: 3600)
BROADCAST_CONNECTION (log/reverb/pusher)
```

---

## 15. Vývojové príkazy

```bash
# Backend (cez Docker)
docker-compose up -d
docker-compose exec php php artisan migrate
docker-compose exec php php artisan test
docker-compose exec php php artisan optimize:clear   # po zmene .env
docker-compose exec php php artisan tinker

# Frontend
npm run dev         # Vite watch mód
npm run build       # Produkčný build
npm run lint        # ESLint + auto-fix

# Verziovanie
npm run version:patch    # 1.0.97 → 1.0.98
npm run version:minor    # 1.0.97 → 1.1.0
```

---

## 16. Štruktúra súborov (referencia)

```
app/
  Http/Controllers/       — tenké controllery, delegujú na services
  Http/Middleware/         — EnsureStoreOwnership, EnsureAdminRole, EnsurePlanAllows*, AuditLog
  Services/BtcPay/         — 13 service tried obaľujúcich Greenfield API
  Models/                  — Eloquent modely
  Jobs/                    — asynchrónne jobs (export, webhooks)
config/
  plans.php, pricing.php, sanctum.php, session.php
database/
  migrations/, factories/, seeders/
resources/js/
  app.ts, bootstrap.ts
  router/, store/, pages/, components/, services/, locales/
routes/
  api.php                  — všetky API routes
```

---

## 17. Testovanie

**Vzory:**
```php
// Mock BTCPay HTTP volania
Http::fake(['btcpay.example.com/api/v1/stores' => Http::response([...])]);

// Test multi-tenant izolácie
$this->actingAs($otherUser)->get("/api/stores/{$store->id}")
    ->assertStatus(403);
```

- PHPUnit testy v `tests/`
- `Http::fake()` pre BTCPay API mocking
- Pokrytie: auth, store CRUD, exporty
