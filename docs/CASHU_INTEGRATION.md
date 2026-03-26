# Cashu — Integrácia do Satflux ako nový typ wallet connection

## Kontex a rozdiel oproti Blink/Aqua

**Blink/Aqua flow:**
Merchant zadá connection string/descriptor → uloží sa ako `encrypted_secret` do `wallet_connections` → bot nakonfiguruje BTCPay → ak bot zlyhá → support zásah.

**Cashu flow:**
Merchant zadá Mint URL + Unit + Lightning Address → Satflux okamžite zavolá BTCPay Cashu Plugin API → hotovo. Žiadny secret, žiadny bot, žiadny support.

Cashu nastavenia žijú v BTCPay (Cashu plugin DB schéma), nie v Satflux DB.
Satflux iba vie, že store má `wallet_type = 'cashu'`.

---

## Čo existuje na BTCPay strane (Cashu Plugin API)

```
Base: https://{btcpay_host}/api/v1/stores/{btcpayStoreId}/plugins/cashu
Auth: token {merchant_btcpay_api_key}   ← oprávnenie CanModifyStoreSettings
```

### GET `/settings`
```json
{
  "storeId": "string",
  "mintUrl": "https://mint.example.com",
  "unit": "sat",
  "lightningAddress": "merchant@blink.sv",
  "enabled": true
}
```

### PUT `/settings`
```json
// Request body:
{ "mintUrl": "https://...", "unit": "sat|usd", "lightningAddress": "user@domain", "enabled": true }

// Validácia na BTCPay strane:
// - mintUrl musí začínať https://
// - lightningAddress musí mať formát user@domain
// - unit musí byť "sat" alebo "usd"
// Chyba: 400 { "error": "MintUrl must use HTTPS" }
```

### GET `/payments?limit=50&offset=0&settlementState=SETTLED|PENDING|FAILED`
```json
{
  "total": 42, "offset": 0, "limit": 50,
  "items": [{
    "quoteId": "abc-xyz",
    "invoiceId": "btcpay-invoice-id",
    "amountSats": 1000,
    "unit": "sat",
    "state": "PAID",
    "settlementState": "SETTLED|PENDING|FAILED",
    "settlementError": null,
    "settlementReference": "preimage-hex",
    "createdAt": "...", "paidAt": "...", "settledAt": "..."
  }]
}
```

### GET `/payments/{quoteId}` — detail jednej platby

### POST `/payments/{quoteId}/retry` — retry FAILED platby
```json
{ "settled": true, "error": null }
```

---

## Čo treba zmeniť v Satflux

### 1. Databázová migrácia

**Súbor:** nová migrácia v `database/migrations/`

```php
// Zmena 1: stores.wallet_type — pridaj 'cashu'
$table->enum('wallet_type', ['blink', 'aqua_boltz', 'cashu'])->nullable()->change();

// Zmena 2: wallet_connections.type — pridaj 'cashu' (pre prípad budúcej rekonfigurácie cez settings page)
// Ale: pri Cashu sa wallet_connection NEVYTVÁRA — je to len pre prípad, že ho budeme potrebovať
// Zatiaľ stačí zmeniť stores.wallet_type
```

### 2. Backend — nová service

**Súbor:** `app/Services/BtcPay/CashuService.php`

```php
class CashuService
{
    public function __construct(private BtcPayClient $client) {}

    public function getSettings(string $btcpayStoreId, string $apiKey): ?array
    {
        return $this->client->get(
            "/api/v1/stores/{$btcpayStoreId}/plugins/cashu/settings",
            $apiKey
        );
    }

    public function saveSettings(string $btcpayStoreId, array $data, string $apiKey): array
    {
        // $data = ['mintUrl' => ..., 'unit' => ..., 'lightningAddress' => ..., 'enabled' => true]
        return $this->client->put(
            "/api/v1/stores/{$btcpayStoreId}/plugins/cashu/settings",
            $data,
            $apiKey
        );
    }

    public function listPayments(string $btcpayStoreId, string $apiKey, array $params = []): array
    {
        // $params: limit, offset, settlementState
        return $this->client->get(
            "/api/v1/stores/{$btcpayStoreId}/plugins/cashu/payments",
            $apiKey,
            $params
        );
    }

    public function retryPayment(string $btcpayStoreId, string $quoteId, string $apiKey): array
    {
        return $this->client->post(
            "/api/v1/stores/{$btcpayStoreId}/plugins/cashu/payments/{$quoteId}/retry",
            [],
            $apiKey
        );
    }
}
```

### 3. Backend — StoreController::store() — Cashu vetva

**Súbor:** `app/Http/Controllers/StoreController.php`

Pridaj validáciu Cashu polí do `StoreRequest` (alebo priamo v controlleri):
```php
// Ak wallet_type === 'cashu':
'mint_url'          => 'required_if:wallet_type,cashu|url|starts_with:https',
'unit'              => 'required_if:wallet_type,cashu|in:sat,usd',
'lightning_address' => 'required_if:wallet_type,cashu|string|regex:/^[^@]+@[^@]+$/',
```

V `store()` metóde, za vytvorením lokálneho Store záznamu, pridaj vetvu:

```php
// Existujúci kód pre Blink/Aqua:
if ($request->wallet_type !== 'cashu' && $request->filled('connection_string')) {
    // ... existujúca logika wallet_connection ...
}

// Nová Cashu vetva:
if ($request->wallet_type === 'cashu') {
    $cashuService = app(CashuService::class);
    $cashuService->saveSettings(
        $btcpayStore['id'],   // btcpay_store_id čerstvé z BTCPay
        [
            'mintUrl'          => $request->mint_url,
            'unit'             => $request->unit,
            'lightningAddress' => $request->lightning_address,
            'enabled'          => true,
        ],
        $user->btcpay_api_key
    );
    // Žiadna wallet_connection, žiadny bot, žiadny support
}
```

### 4. Backend — nový CashuController (pre settings page existujúceho store)

**Súbor:** `app/Http/Controllers/CashuController.php`

```php
class CashuController extends Controller
{
    public function getSettings(Store $store): JsonResponse
    {
        // Zavolá CashuService::getSettings s merchant API kľúčom
        // Vráti mintUrl, unit, lightningAddress, enabled
    }

    public function updateSettings(Request $request, Store $store): JsonResponse
    {
        // Validácia: mintUrl (https), unit (sat|usd), lightningAddress (user@domain)
        // Zavolá CashuService::saveSettings
        // Vráti aktualizované nastavenia
    }

    public function listPayments(Request $request, Store $store): JsonResponse
    {
        // Query params: limit, offset, settlementState
        // Zavolá CashuService::listPayments
    }

    public function retryPayment(Store $store, string $quoteId): JsonResponse
    {
        // Zavolá CashuService::retryPayment
    }
}
```

**Pridaj do `routes/api.php`** (v bloku `auth:sanctum`):
```php
Route::middleware([EnsureStoreOwnership::class])->prefix('stores/{store}/cashu')->group(function () {
    Route::get('settings',                     [CashuController::class, 'getSettings']);
    Route::put('settings',                     [CashuController::class, 'updateSettings']);
    Route::get('payments',                     [CashuController::class, 'listPayments']);
    Route::post('payments/{quoteId}/retry',    [CashuController::class, 'retryPayment']);
});
```

### 5. Store model

**Súbor:** `app/Models/Store.php`

Pridaj `'cashu'` do `wallet_type` cast/enum.

---

## Frontend — čo zmeniť

### 6. Store Creation Wizard — `resources/js/pages/stores/Create.vue`

**Krok 2 — výber wallet typu:**

Pridaj tretiu možnosť vedľa Blink a Aqua:

```vue
<!-- Cashu option -->
<label>
  <input type="radio" v-model="form.wallet_type" value="cashu" />
  Cashu Ecash
  <span class="text-sm text-gray-500">Platby cez Cashu ecash, automaticky preposielané na Lightning adresu</span>
</label>

<!-- Cashu polia (zobrazia sa len ak wallet_type === 'cashu') -->
<div v-if="form.wallet_type === 'cashu'">
  <input v-model="form.mint_url"
         placeholder="https://mint.minibits.cash/Bitcoin"
         label="Cashu Mint URL" />

  <select v-model="form.unit" label="Jednotka">
    <option value="sat">Satoshi (sat)</option>
    <option value="usd">USD (stablesats)</option>
  </select>

  <input v-model="form.lightning_address"
         placeholder="merchant@blink.sv"
         label="Lightning Address pre výplatu" />
</div>
```

**Validácia v kroku 2 pre Cashu:**
```typescript
if (form.wallet_type === 'cashu') {
  if (!form.mint_url.startsWith('https://')) errors.mint_url = 'Mint URL musí začínať https://'
  if (!form.lightning_address.match(/^[^@]+@[^@]+$/)) errors.lightning_address = 'Formát: user@domain'
  if (!form.unit) errors.unit = 'Vyber jednotku'
}
```

**Submit — pridaj Cashu polia do request body:**
```typescript
const payload = {
  name: form.name,
  // ...ostatné polia...
  wallet_type: form.wallet_type,
  // Cashu-specific (backend ignoruje ak nie sú relevantné):
  mint_url: form.mint_url,
  unit: form.unit,
  lightning_address: form.lightning_address,
  // Blink/Aqua (prázdne pre Cashu):
  connection_string: form.connection_string,
}
```

### 7. WalletConnectionForm — `resources/js/components/stores/WalletConnectionForm.vue`

Pre existujúce store s `wallet_type === 'cashu'` — namiesto connection string formulára zobraz Cashu settings formulár (rovnaké polia: Mint URL, Unit, Lightning Address) a pri Save volaj `PUT /stores/{store}/cashu/settings`.

Cashu nemá žiadny "secret" — nepotrebuje password confirmation pre reveal.
Stav je vždy `connected` (nie `pending`/`needs_support`).

### 8. Cashu Payments page — nová stránka

**Súbor:** `resources/js/pages/stores/CashuPayments.vue`

Tabuľka platieb s:
- Stĺpce: Dátum vytvorenia | Suma (sat) | Stav | Chyba | Akcie
- Filter: `settlementState` dropdown — Všetky / SETTLED / PENDING / FAILED
- Stránkovanie
- Farebné označenie stavov: SETTLED=zelená, PENDING=šedá, FAILED=červená
- Retry tlačidlo pre FAILED (len ak `settlementError` neobsahuje "proofs are not available")

**Pridaj do routera:**
```typescript
{ path: '/stores/:storeId/cashu', component: () => import('@/pages/stores/CashuPayments.vue'), meta: { requiresAuth: true } }
```

**Pridaj do store navigácie:** link "Cashu platby" (viditeľný len ak `store.wallet_type === 'cashu'`)

### 9. Pinia store — `resources/js/store/stores.ts`

Pridaj `'cashu'` do `wallet_type` union typu:
```typescript
wallet_type: 'blink' | 'aqua_boltz' | 'cashu' | null
```

---

## Čo Cashu nepotrebuje (na rozdiel od Blink/Aqua)

| Vec | Blink/Aqua | Cashu |
|-----|-----------|-------|
| `wallet_connections` DB záznam | ✓ áno | ✗ nie |
| `encrypted_secret` | ✓ áno | ✗ nie |
| Config bot | ✓ áno | ✗ nie |
| Support zásah | možný | ✗ nie |
| Status pending/needs_support | ✓ áno | ✗ nie |
| Password na reveal | ✓ áno | ✗ nie |
| Test connection endpoint | ✓ áno | ✗ nie (BTCPay overí pri save) |

---

## Chybové stavy

| Situácia | Správanie |
|----------|-----------|
| BTCPay vráti 400 pri ukladaní settings | Zobraz error správu z `{ "error": "..." }` v UI |
| Cashu plugin nie je nainštalovaný na BTCPay | `saveSettings` hodí HTTP chybu → zobraz "Cashu plugin nie je dostupný" |
| Platba FAILED | Zobraz `settlementError`, ponúkni Retry tlačidlo ak sú dostupné proofs |
| Retry bez proofs | BTCPay vráti 400 s "proofs are not available" → skry Retry tlačidlo |
