# Satflux API — Inštrukcie pre agenta

Tento dokument popisuje API Satflux panelu a integrované API BTCPay Servera.
Agent pracuje na projekte **satflux.io** — multi-tenant Laravel + Vue SPA paneli pre správu BTCPay Server obchodov.

---

## 1. Architektúra a základné pravidlá

### Stack
- **Backend**: Laravel 12, PostgreSQL, Redis
- **Frontend**: Vue 3 + TypeScript + Vite + TailwindCSS
- **Auth**: Laravel Sanctum (SPA cookie, nie tokeny)
- **Cesta k backendu**: `app/` — controllers, services, models
- **Cesta k frontendu**: `resources/js/` — Vue SPA

### Absolútne pravidlá
1. **Nikdy neexpozuj `btcpay_store_id`** na frontende — vždy používaj lokálne UUID z tabuľky `stores`
2. **Každá store-scoped route musí mať middleware `EnsureStoreOwnership`**
3. **BTCPay operácie vždy cez `BtcPayClient`** — nikdy priamo cez HTTP
4. **`php artisan optimize:clear`** po každej zmene `.env` alebo config súborov
5. **Dual API key**: server-level kľúč len na provisioning; merchant kľúč na store operácie
6. Testovanie BTCPay HTTP volaní: `Http::fake()` — pozri existujúce store creation testy

### Príkazy (cez Docker)
```bash
docker-compose exec php php artisan migrate          # migruj DB
docker-compose exec php php artisan test             # všetky testy
docker-compose exec php php artisan test --filter=X  # konkrétny test
docker-compose exec php php artisan optimize:clear   # vyčisti cache
docker-compose exec php php artisan tinker           # REPL shell
npm run dev                                          # Vite dev server
npm run build                                        # produkčný build
npm run lint                                         # ESLint fix
```

---

## 2. Autentifikácia — Satflux API

### SPA (prehliadač)
1. `GET /sanctum/csrf-cookie` → nastaví `XSRF-TOKEN` cookie
2. `POST /api/auth/login` → nastaví `laravel_session` cookie (HttpOnly)
3. Všetky ďalšie requesty: prehliadač posiela `laravel_session` + `X-XSRF-TOKEN` header automaticky

### Panel API kľúče (programatický prístup)
```
Authorization: Bearer <user_api_key>
```
- Kľúče spravuje `UserApiKeyController`
- Vyžadujú plán s `user_api_keys` funkciou

### Lokálny vývoj
```
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost:8080
```

---

## 3. Satflux API Endpointy

Základná URL: `/api`
Všetky autentifikované endpointy: `auth:sanctum` middleware

### 3.1 Auth

| Metóda | URL | Auth | Popis |
|--------|-----|------|-------|
| POST | `/auth/register` | nie | Registrácia (rate limited) |
| POST | `/auth/login` | nie | Prihlásenie email+heslo |
| POST | `/auth/logout` | áno | Odhlásenie |
| POST | `/lnurl-auth/challenge` | nie | LNURL-auth QR kód |
| GET | `/lnurl-auth/verify` | nie | Verifikácia LNURL podpisu |
| POST | `/nostr-auth/challenge` | nie | Nostr autentifikácia |
| GET | `/lnurl-auth/enabled` | nie | Je LNURL-auth aktívne? |
| GET | `/lnurl-auth/challenge-status/{k1}` | nie | Polling stavu (max 60/min) |

### 3.2 Používateľ

| Metóda | URL | Auth | Popis |
|--------|-----|------|-------|
| GET | `/user` | áno | Profil prihláseného užívateľa |
| PUT | `/user` | áno | Aktualizácia profilu |
| PUT | `/user/password` | áno | Zmena hesla |
| GET | `/user/limits` | áno | Limity plánu |
| GET | `/user/api-keys` | áno | Zoznam panel API kľúčov |
| POST | `/user/api-keys` | áno + plán | Vytvorenie API kľúča |
| DELETE | `/user/api-keys/{id}` | áno | Zmazanie API kľúča |

**Odpoveď GET `/user`:**
```json
{
  "id": "uuid",
  "email": "user@example.com",
  "name": "Meno",
  "role": "user|admin|support",
  "subscription_plan": "free|pro|enterprise",
  "email_verified_at": "2024-01-01T00:00:00Z"
}
```

### 3.3 Správa obchodov

| Metóda | URL | Middleware | Popis |
|--------|-----|-----------|-------|
| GET | `/stores` | auth | Zoznam obchodov užívateľa |
| POST | `/stores` | auth + `EnsureStoreLimit` | Vytvorenie obchodu |
| GET | `/stores/{store}` | auth + `EnsureStoreOwnership` | Detail obchodu |
| GET | `/stores/{store}/settings` | auth + ownership | Nastavenia BTCPay store |
| PUT | `/stores/{store}/settings` | auth + ownership | Aktualizácia nastavení |
| DELETE | `/stores/{store}` | auth + ownership | Zmazanie obchodu |
| POST | `/stores/{store}/logo` | auth + ownership | Upload loga (multipart) |
| DELETE | `/stores/{store}/logo` | auth + ownership | Zmazanie loga |

**Odpoveď GET `/stores`:**
```json
[{
  "id": "local-uuid",
  "name": "Moj obchod",
  "wallet_type": "blink|boltz|lnd|...",
  "created_at": "...",
  "dashboard": { "invoices_today": 5, "revenue_btc": "0.001" }
}]
```

> ⚠️ `btcpay_store_id` sa **nikdy** neposiela na frontend. Vždy používaj `id` (lokálne UUID).

### 3.4 Faktúry

| Metóda | URL | Middleware | Popis |
|--------|-----|-----------|-------|
| GET | `/stores/{store}/invoices` | auth + ownership | Zoznam faktúr (stránkovaný) |
| GET | `/stores/{store}/invoices/export` | auth + ownership | Export CSV |

**Query parametre pre `/invoices`:**
```
?limit=25&offset=0&status=New|Processing|Settled|Expired|Invalid
&dateFrom=2024-01-01&dateTo=2024-12-31
&searchText=text
```

**Odpoveď:**
```json
{
  "total": 100,
  "items": [{
    "id": "btcpay-invoice-id",
    "status": "Settled",
    "amount": "0.001",
    "currency": "BTC",
    "createdTime": "...",
    "expirationTime": "...",
    "checkoutLink": "https://..."
  }]
}
```

### 3.5 Exporty (Pro+)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/stores/{store}/exports` | Zoznam exportov |
| POST | `/stores/{store}/exports` | Vytvorenie exportu (async job) |
| GET | `/exports/{export}/download` | Stiahnutie súboru |
| POST | `/exports/{export}/retry` | Retry zlyhnutého exportu |
| DELETE | `/exports/{export}` | Zmazanie exportu |

**Body pre POST `/exports`:**
```json
{
  "format": "csv|xlsx",
  "date_from": "2024-01-01",
  "date_to": "2024-12-31"
}
```

### 3.6 Aplikácie (PoS, Crowdfund)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/stores/{store}/apps` | Zoznam aplikácií |
| POST | `/stores/{store}/apps` | Vytvorenie aplikácie |
| GET | `/stores/{store}/apps/{app}` | Detail aplikácie |
| PUT | `/stores/{store}/apps/{app}` | Aktualizácia |
| DELETE | `/stores/{store}/apps/{app}` | Zmazanie |

**Body pre POST `/apps`:**
```json
{
  "type": "PointOfSale|Crowdfund",
  "name": "Názov aplikácie",
  "settings": {}
}
```

### 3.7 Lightning Adresy

| Metóda | URL | Plán | Popis |
|--------|-----|------|-------|
| GET | `/stores/{store}/lightning-addresses` | Free+ | Zoznam |
| GET | `/stores/{store}/lightning-addresses/{username}` | Free+ | Detail |
| POST | `/stores/{store}/lightning-addresses/{username}` | Pro+ | Vytvorenie |
| PUT | `/stores/{store}/lightning-addresses/{username}` | Pro+ | Aktualizácia |
| DELETE | `/stores/{store}/lightning-addresses/{username}` | Free+ | Zmazanie |

Username = časť pred `@` v lightning adrese.

### 3.8 Wallet Connection

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/stores/{store}/wallet-connection` | Stav pripojenia peňaženky |
| POST | `/stores/{store}/wallet-connection` | Vytvorenie pripojenia |
| POST | `/stores/{store}/wallet-connection/reveal` | Odhalenie secret tokenu |
| POST | `/stores/{store}/wallet-connection/test` | Test konfigurácie |
| POST | `/stores/{store}/wallet-connection/configure` | Konfigurácia Lightning |
| DELETE | `/stores/{store}/wallet-connection` | Zmazanie |

### 3.9 PoS Terminály a Objednávky

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/stores/{store}/pos-terminals` | Zoznam terminálov |
| POST | `/stores/{store}/pos-terminals` | Vytvorenie terminálu |
| PUT | `/stores/{store}/pos-terminals/{id}` | Aktualizácia (Pro+) |
| DELETE | `/stores/{store}/pos-terminals/{id}` | Zmazanie |
| GET | `/stores/{store}/pos-orders` | Zoznam objednávok |
| POST | `/stores/{store}/pos-orders` | Nová objednávka |

### 3.10 Stripe (Pro+)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/stores/{store}/stripe/settings` | Nastavenia Stripe |
| PUT | `/stores/{store}/stripe/settings` | Uloženie nastavení |
| DELETE | `/stores/{store}/stripe/settings` | Zmazanie |
| POST | `/stores/{store}/stripe/test` | Test pripojenia |
| POST | `/stores/{store}/stripe/webhook/register` | Registrácia webhook |

### 3.11 Store API Kľúče

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/stores/{store}/api-keys` | Zoznam kľúčov |
| POST | `/stores/{store}/api-keys` | Vytvorenie kľúča |
| GET | `/stores/{store}/api-keys/{id}` | Detail kľúča |
| DELETE | `/stores/{store}/api-keys/{id}` | Zmazanie |
| POST | `/stores/{store}/api-keys/{id}/regenerate` | Regenerácia |

### 3.12 Dashboard a Štatistiky

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/dashboard` | Globálny dashboard |
| GET | `/dashboard/stats` | Štatistiky |
| GET | `/stores/{store}/dashboard` | Store dashboard |
| GET | `/stores/{store}/stats` | Store štatistiky |
| GET | `/stores/{store}/checklist` | Setup checklist |
| PUT | `/stores/{store}/checklist/{itemKey}` | Aktualizácia položky |

### 3.13 SatoshiTickets (Eventy)

Prefix: `/stores/{store}/tickets/`

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `events` | Zoznam eventov |
| POST | `events` | Vytvorenie eventu |
| GET | `events/{id}` | Detail eventu |
| PUT | `events/{id}` | Aktualizácia |
| DELETE | `events/{id}` | Zmazanie |
| PUT | `events/{id}/toggle` | Aktivácia/deaktivácia |
| GET | `events/{id}/ticket-types` | Typy lístkov |
| POST | `events/{id}/ticket-types` | Nový typ |
| GET | `events/{id}/tickets` | Zoznam lístkov |
| POST | `events/{id}/tickets/{num}/check-in` | Check-in |
| GET | `events/{id}/orders` | Objednávky |

### 3.14 Správy (Notifikácie)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/messages` | Zoznam správ |
| GET | `/messages/count` | Počet neprečítaných |
| PATCH | `/messages/{id}/read` | Označ ako prečítané |
| POST | `/messages/mark-all-read` | Označ všetky |

### 3.15 Verejné endpointy (bez auth)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/health` | Health check |
| GET | `/version` | Verzia aplikácie |
| GET | `/pricing` | Cenník (z config) |
| GET | `/plan-features` | Funkcie plánov |
| POST | `/webhooks/btcpay` | BTCPay webhook (HMAC-SHA256) |
| GET | `/documentation` | Dokumentácia |
| GET | `/faq` | FAQ |

### 3.16 Admin endpointy (role: admin)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/admin/stats` | Systémové štatistiky |
| GET | `/admin/users` | Zoznam používateľov |
| GET | `/admin/users/{id}` | Detail používateľa |
| PUT | `/admin/users/{id}` | Aktualizácia |
| DELETE | `/admin/users/{id}` | Zmazanie |

### 3.17 Support endpointy (role: support alebo admin)

| Metóda | URL | Popis |
|--------|-----|-------|
| GET | `/support/wallet-connections` | Pripojenia všetkých obchodov |
| POST | `/support/wallet-connections/{id}/reveal` | Odhalenie tokenu |
| PUT | `/support/wallet-connections/{id}/mark-connected` | Označiť ako pripojené |

---

## 4. BTCPay Greenfield API (cez BtcPayClient)

**DÔLEŽITÉ**: BTCPay API sa vždy volá zo servera (nikdy z frontendu priamo).

### Autentifikácia voči BTCPay
```
Authorization: token <api_key>
```

Dva typy kľúčov:
- **Server-level** (`BTCPAY_API_KEY` v `.env`): provisioning užívateľov a obchodov
- **Merchant-level** (`User.btcpay_api_key`, šifrovaný v DB): store operácie

### Najdôležitejšie BTCPay endpointy

**Stores:**
```
GET    /api/v1/stores                      # Zoznam obchodov
POST   /api/v1/stores                      # Vytvorenie
GET    /api/v1/stores/{storeId}            # Detail
PUT    /api/v1/stores/{storeId}            # Aktualizácia
DELETE /api/v1/stores/{storeId}            # Zmazanie
```

**Faktúry:**
```
GET    /api/v1/stores/{storeId}/invoices             # Zoznam (paginovaný)
POST   /api/v1/stores/{storeId}/invoices             # Vytvorenie
GET    /api/v1/stores/{storeId}/invoices/{invoiceId} # Detail
PATCH  /api/v1/stores/{storeId}/invoices/{invoiceId} # Aktualizácia stavu
```

**Požadované oprávnenia merchant kľúča:**
```
btcpay.store.cancreateinvoice
btcpay.store.canviewstoresettings
btcpay.store.canmodifyinvoices
btcpay.store.canmodifystoresettings
btcpay.store.canviewinvoices
```

**Používatelia (server kľúč):**
```
POST   /api/v1/users                       # Vytvorenie BTCPay užívateľa
GET    /api/v1/users/me                    # Aktuálny užívateľ
POST   /api/v1/users/{userId}/api-keys     # Vytvorenie API kľúča pre užívateľa
```

**Apps:**
```
GET    /api/v1/stores/{storeId}/apps
POST   /api/v1/stores/{storeId}/apps
PUT    /api/v1/stores/{storeId}/apps/{appId}
DELETE /api/v1/stores/{storeId}/apps/{appId}
```

**Lightning:**
```
GET    /api/v1/stores/{storeId}/lightning/BTC/info
GET    /api/v1/stores/{storeId}/payment-methods
```

### BtcPayClient použitie (PHP)
```php
// Štandardné použitie v service triede
$response = $this->client->get('/api/v1/stores', $merchantApiKey);
$response = $this->client->post('/api/v1/stores', $data, $serverApiKey);

// Prepnutie kľúča
$this->client->setApiKey($merchantApiKey);
$response = $this->client->get('/api/v1/stores/{id}/invoices');
```

---

## 5. Cashu Plugin API

Plugin je nainštalovaný na BTCPay Serveri. Prístupný priamo na BTCPay inštancii.

### Autentifikácia
- **Cookie** (UI prihlásenie) alebo
- **BTCPay API kľúč** s oprávnením `CanModifyStoreSettings` (Greenfield)

Header:
```
Authorization: token <btcpay_api_key>
```

### Endpointy

**Základná cesta**: `https://{btcpay_host}/api/v1/stores/{storeId}/plugins/cashu`

#### GET `/settings`
Získanie Cashu nastavení obchodu.

**Odpoveď 200:**
```json
{
  "storeId": "abc123",
  "mintUrl": "https://mint.example.com",
  "unit": "sat",
  "lightningAddress": "merchant@blink.sv",
  "enabled": true
}
```

#### PUT `/settings`
Uloženie nastavení.

**Body:**
```json
{
  "mintUrl": "https://mint.example.com",
  "unit": "sat",
  "lightningAddress": "merchant@blink.sv",
  "enabled": true
}
```

Validácia:
- `mintUrl` musí začínať `https://`
- `lightningAddress` musí mať formát `user@domain`
- `unit` musí byť `"sat"` alebo `"usd"`

**Odpoveď 400** pri validačnej chybe:
```json
{ "error": "MintUrl must use HTTPS" }
```

#### GET `/payments`
Zoznam Cashu platieb, najnovšie prvé.

**Query parametre:**
```
?limit=50&offset=0&settlementState=SETTLED|PENDING|FAILED
```
Limit: 1–200 (default 50).

**Odpoveď 200:**
```json
{
  "total": 42,
  "offset": 0,
  "limit": 50,
  "items": [{
    "quoteId": "abc-xyz",
    "invoiceId": "btcpay-invoice-id",
    "amountSats": 1000,
    "unit": "sat",
    "state": "PAID",
    "settlementState": "SETTLED",
    "settlementError": null,
    "settlementReference": "preimage-hex",
    "createdAt": "2024-01-01T10:00:00Z",
    "paidAt": "2024-01-01T10:01:00Z",
    "settledAt": "2024-01-01T10:01:05Z"
  }]
}
```

**Hodnoty `settlementState`:**
- `PENDING` — čaká na platbu alebo spracovanie
- `SETTLED` — úspešne splatené merchant-ovi
- `FAILED` — zlyhalo (pozri `settlementError`)

#### GET `/payments/{quoteId}`
Detail jednej platby. Odpoveď ako jeden objekt z `items` vyššie.

**Odpoveď 404:**
```json
{ "error": "Payment request not found" }
```

#### POST `/payments/{quoteId}/retry`
Manuálny retry zlyhnutej platby (keď sú dostupné Cashu proofs).

Funguje len ak:
- `settlementState == "FAILED"` a `mintedProofsJson` nie je prázdny

**Odpoveď 200:**
```json
{ "settled": true, "error": null }
```

**Odpoveď 400** ak retry nie je možný:
```json
{ "error": "Cannot retry: proofs are not available (already spent or never minted)" }
```

### Cashu Checkout Polling (verejný endpoint)

`GET /plugins/cashu/poll/{quoteId}`

Volá sa každé 2 sekundy z checkout stránky. Bez autentifikácie.

**Odpoveď:**
```json
{ "paid": false, "error": null }
```

---

## 6. Dátové modely

### Store (Satflux DB)
```
id                 UUID (lokálne, používa sa na frontende)
user_id            FK → users
btcpay_store_id    string (NIKDY neposielaj na frontend)
name               string
wallet_type        string
created_at, updated_at
```

### CashuStoreSettings (BTCPay Plugin DB — schéma "BTCPayServer.Plugins.Cashu")
```
StoreId            PK varchar(100)
MintUrl            varchar(500) — musí byť HTTPS
Unit               varchar(20) — "sat" alebo "usd"
LightningAddress   varchar(500) — formát user@domain
Enabled            boolean
CreatedAt, UpdatedAt
```

### CashuPaymentRequest (BTCPay Plugin DB)
```
QuoteId            PK — ID mint quote
InvoiceId          — ID BTCPay faktúry
StoreId
AmountSats, Unit
Bolt11Invoice      — Lightning invoice od mint-u
State              UNPAID → PAID
SettlementState    PENDING → SETTLED alebo FAILED
SettlementError    chybová správa pri FAILED
SettlementReference — preimage po úspešnom platení
MintedProofsJson   — Cashu proofs (crash recovery, vymaže sa po melt-e)
MeltQuoteId        — melt quote ID
ForwardBolt11      — BOLT11 na merchant Lightning adresu
CreatedAt, PaidAt, SettledAt
```

---

## 7. Chybové odpovede

### Satflux API
```json
{ "message": "Unauthenticated." }          // 401
{ "message": "This action is unauthorized." } // 403
{ "message": "Validation error", "errors": { "field": ["..."] } } // 422
```

### BTCPay Greenfield API
```json
{ "message": "Error description" }          // 4xx/5xx
```

### Cashu Plugin API
```json
{ "error": "Error description" }            // 4xx
```

---

## 8. Plánové obmedzenia

| Funkcia | Free | Pro | Enterprise |
|---------|------|-----|------------|
| Panel API kľúče | nie | áno | áno |
| Lightning adresy | čítaj | plné | plné |
| Exporty | nie | áno | áno |
| Stripe integrácia | nie | áno | áno |
| Offline platby (PoS) | nie | áno | áno |
| PDF reporty | nie | áno | áno |

Middleware pre obmedzenia:
- `EnsurePlanAllowsUserApiKeyCreation`
- `EnsurePlanAllowsLnAddressCreation`
- `EnsurePlanAllowsExportsAccess`
- `EnsurePlanAllowsStripe`
- `EnsurePlanAllowsOfflinePaymentMethods`

---

## 9. Webhooks

### BTCPay → Satflux
`POST /api/webhooks/btcpay` — bez autentifikácie, overenie HMAC-SHA256

Keď je `BTCPAY_WEBHOOK_SECRET` nastavený, každý request musí mať:
```
BTCPay-Sig: sha256=<hmac>
```

Spracovávané eventy: `InvoiceSettled`, `InvoiceExpired`, `InvoiceProcessing`, atď.

---

## 10. Frontend — kľúčové konvencie

### Pinia stores
- `auth.ts` — stav prihláseného užívateľa, login/logout
- `stores.ts` — zoznam obchodov, aktuálny obchod, dashboard
- `flash.ts` — toast notifikácie
- `onboarding.ts` — onboarding wizard

### Axios client (`resources/js/services/api.ts`)
```typescript
// Nastavenie
axios.defaults.withCredentials = true
axios.defaults.headers['X-XSRF-TOKEN'] = getCookie('XSRF-TOKEN')

// 401 → automaticky redirect na /login
// Všetky requesty sú na /api/*
```

### Router (`resources/js/router/index.ts`)
- `meta.requiresAuth: true` → ochrana routy
- 401 odpovede → redirect na `/login`

### Lokalizácia
- Vue-i18n, súbory v `resources/js/locales/`
- `POST /api/locale` — nastavenie jazyka

---

## 11. Bezpečnosť — checklist pre agenta

Pri každej novej route alebo feature overiť:
- [ ] Store-scoped route má `EnsureStoreOwnership` middleware
- [ ] `btcpay_store_id` sa nikde neobjavuje v JSON odpovediach na frontend
- [ ] BTCPay HTTP volania idú cez `BtcPayClient`, nie priamy HTTP
- [ ] Senzitívne akcie majú `AuditLog` middleware
- [ ] Nové fronendové routes s citlivými dátami majú `meta.requiresAuth: true`
- [ ] Cashu API volania používajú BTCPay API kľúč s `CanModifyStoreSettings`
- [ ] Webhook endpointy overujú HMAC podpis
