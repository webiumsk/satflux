# satflux.io - Architecture & Technical Documentation

**For AI Agents & Developers**: This document provides a comprehensive overview of how the satflux.io application works, including data flows, component relationships, and implementation details.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture Layers](#architecture-layers)
3. [Authentication & Authorization](#authentication--authorization)
4. [BTCPay Server Integration](#btcpay-server-integration)
5. [User Registration & Provisioning Flow](#user-registration--provisioning-flow)
6. [Store Creation & Management](#store-creation--management)
7. [Database Schema & Models](#database-schema--models)
8. [Frontend Architecture](#frontend-architecture)
9. [API Layer & Middleware](#api-layer--middleware)
10. [Security Model](#security-model)
11. [Data Flow Diagrams](#data-flow-diagrams)

---

## System Overview

**satflux.io** is a multi-tenant control panel for managing BTCPay Server stores. It acts as a bridge between merchants (satflux.io users) and BTCPay Server, providing a simplified interface while maintaining security and isolation.

### Tech Stack

- **Backend**: Laravel 11 (PHP 8.3), Sanctum, PostgreSQL, Redis
- **Frontend**: Vue 3 + TypeScript + Vite + TailwindCSS (SPA)
- **Infrastructure**: Docker Compose (nginx + php-fpm + postgres + redis)
- **External API**: BTCPay Server Greenfield API

### Key Concepts

1. **Multi-tenancy**: Each merchant has isolated stores. Isolation enforced at application layer (not database schema).
2. **Dual API Keys**: Server-level key (unrestricted, for provisioning) + Merchant-level keys (scoped, for store operations).
3. **UUID-based routing**: Frontend never sees `btcpay_store_id` - only local UUIDs for security.
4. **Stateful API**: Sanctum SPA cookies for authentication (not stateless tokens).

---

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (Vue SPA)                       │
│  - Vue Router (client-side routing)                         │
│  - Pinia stores (auth, stores state)                        │
│  - Axios (HTTP client with withCredentials=true)            │
└───────────────────────┬─────────────────────────────────────┘
                        │ HTTPS/HTTP
                        │ Cookies: laravel_session, XSRF-TOKEN
┌───────────────────────▼─────────────────────────────────────┐
│              Laravel Backend (API Layer)                     │
│  - Sanctum SPA authentication (stateful)                    │
│  - Controllers (business logic)                             │
│  - Services (BTCPay API integration)                        │
│  - Models (Eloquent ORM)                                    │
└───────────────┬───────────────────┬─────────────────────────┘
                │                   │
    ┌───────────▼────────┐  ┌──────▼──────────┐
    │   PostgreSQL DB    │  │  BTCPay Server  │
    │  - Users           │  │  - Stores       │
    │  - Stores (local)  │  │  - Invoices     │
    │  - Metadata        │  │  - Users        │
    └────────────────────┘  └─────────────────┘
```

### Request Flow

1. **Frontend**: Vue component calls Pinia store action
2. **Axios**: HTTP request with cookies (`withCredentials: true`)
3. **Laravel Middleware**: Sanctum validates session cookie
4. **Controller**: Business logic + calls services
5. **Service**: BTCPay API calls or database operations
6. **Response**: JSON back to frontend

---

## Authentication & Authorization

### Sanctum SPA Cookie Authentication

The application uses **Laravel Sanctum's stateful SPA authentication** (not token-based API authentication).

#### How It Works

1. **CSRF Cookie Fetch**: On app boot (`resources/js/bootstrap.ts`), frontend fetches `/sanctum/csrf-cookie` to get `XSRF-TOKEN` cookie.
2. **Login Flow**:
   - User submits credentials via `authStore.login()`
   - Frontend fetches CSRF cookie first (if not cached)
   - `POST /api/auth/login` with email/password
   - Laravel validates credentials, creates session
   - Response sets `laravel_session` cookie (HttpOnly, SameSite=Lax)
   - Frontend stores user object in Pinia store
3. **Authenticated Requests**:
   - Browser automatically sends `laravel_session` cookie
   - Axios sends `X-XSRF-TOKEN` header (from `XSRF-TOKEN` cookie)
   - Sanctum validates session, middleware allows request

#### Key Configuration

**`config/sanctum.php`**:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:8080,...'))
```

- Only domains in this list are treated as "stateful" (session-based auth)
- Requests from other domains use stateless token auth

**`config/session.php`**:

```php
'domain' => env('SESSION_DOMAIN', null), // null for localhost
'secure' => env('SESSION_SECURE_COOKIE', false), // false for HTTP localhost
'same_site' => env('SESSION_SAME_SITE', 'lax'),
```

**Middleware Stack** (`bootstrap/app.php`):

```php
$middleware->statefulApi(); // Enables Sanctum SPA middleware for API routes
```

#### Router Guard (`resources/js/router/index.ts`)

```typescript
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // Fetch user if not already loaded
  if (!authStore.user) {
    await authStore.fetchUser(); // GET /api/user
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: "login" });
  } else {
    next();
  }
});
```

**Important**: `isAuthenticated` is a computed property (`user.value !== null`), not a stored value. The guard checks `!authStore.user` to determine if fetch is needed.

#### Axios Configuration (`resources/js/services/api.ts`)

```typescript
const api = axios.create({
  baseURL: "/api",
  withCredentials: true, // CRITICAL: sends cookies
});

// Response interceptor: redirect to login on 401
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      window.location.href = "/login";
    }
    return Promise.reject(error);
  },
);
```

**CSRF Cookie Strategy**:

- Fetched once on app boot (`bootstrap.ts`)
- Fetched before `login()` and `register()` actions (`auth.ts`)
- **NOT** fetched on every request (cached by browser)

---

## BTCPay Server Integration

### Dual API Key Architecture

The system uses **two types of BTCPay API keys**:

1. **Server-level API key** (`BTCPAY_API_KEY` in `.env`):
   - Unrestricted permissions
   - Used for: user creation, API key creation, store user assignment (admin provisioning)
   - **Never** used for merchant store operations after provisioning

2. **Merchant-level API keys** (`User.btcpay_api_key`):
   - Encrypted in database (`encrypted` cast)
   - Created per-user with permissions: `cancreateinvoice`, `canviewstoresettings`, `canmodifyinvoices`, `canmodifystoresettings`, `canviewinvoices`
   - Used for: all store-scoped operations (create/read/update stores, invoices, apps)

### BTCPay Service Layer

**Location**: `app/Services/BtcPay/`

#### BtcPayClient (`BtcPayClient.php`)

HTTP client wrapper with:

- Retry logic (exponential backoff)
- Rate limit handling
- API key management (`setApiKey()` for per-request override)
- Request/response logging (sanitized, no secrets)

**Key Method**: `setApiKey(string $apiKey)`

- Temporarily switches client to use different API key
- Used by services to switch between server-level and merchant keys

#### Services

1. **UserService** (`UserService.php`):
   - `createUser()`: Create BTCPay user (uses server-level key)
   - `createApiKey()`: Create merchant API key (uses server-level key)
   - `getAdminBtcPayUserId()`: Get admin user ID for store provisioning

2. **StoreService** (`StoreService.php`):
   - `createStore()`: Create store (accepts optional `$userApiKey`)
   - `getStore()`: Get store details (accepts optional `$userApiKey`)
   - `updateStore()`: Update store (accepts optional `$userApiKey`)
   - `addUserToStore()`: Add user as Owner (always uses server-level key)
   - All methods support token override pattern

3. **InvoiceService** (`InvoiceService.php`):
   - `listInvoices()`: List invoices with pagination (accepts optional `$userApiKey`)
   - `getInvoice()`: Get single invoice (accepts optional `$userApiKey`)
   - Cache keys include API key hash to prevent cross-merchant cache pollution

4. **AppService** (`AppService.php`):
   - CRUD operations for BTCPay Apps (Point of Sale, Crowdfund, etc.)
   - All methods accept optional `$userApiKey`

### Token Override Pattern

Services use this pattern to support both server-level and merchant keys:

```php
public function someMethod(string $storeId, ?string $userApiKey = null): array
{
    $originalApiKey = null;
    if ($userApiKey) {
        $originalApiKey = $this->client->getApiKey();
        $this->client->setApiKey($userApiKey);
    }

    try {
        return $this->client->get("/api/v1/stores/{$storeId}");
    } finally {
        if ($userApiKey && $originalApiKey) {
            $this->client->setApiKey($originalApiKey);
        }
    }
}
```

**Why**: BtcPayClient is singleton (DI), so we temporarily switch keys for specific operations.

---

## User Registration & Provisioning Flow

### Complete Registration Flow

```
1. User Registration
   ↓
2. Local User Created (email, password hash)
   ↓
3. Email Verification Link Sent
   ↓
4. User Clicks Link
   ↓
5. Email Verification (EmailVerificationController::verify)
   ├─ Mark email_verified_at
   ├─ Create BTCPay User (with password)
   ├─ Link btcpay_user_id
   └─ Create Merchant API Key
      ↓
6. User Can Create Stores
```

### Step-by-Step Breakdown

#### 1. Registration (`RegisterController::register`)

**File**: `app/Http/Controllers/Auth/RegisterController.php`

- Validates email/password
- Checks if email exists on BTCPay Server (via `UserService::getUserByEmail()`)
- Creates local User record (not yet verified)
- Sends email verification link

**Key Point**: BTCPay user is **NOT** created during registration. It's created during email verification.

#### 2. Email Verification (`EmailVerificationController::verify`)

**File**: `app/Http/Controllers/Auth/EmailVerificationController.php`

**What Happens** (in transaction):

1. **Verify Signature**: Laravel signed URL validation (handles `password` param correctly)
2. **Mark Email Verified**: `$user->markEmailAsVerified()`
3. **Create/Link BTCPay User**:
   ```php
   if (!$user->btcpay_user_id) {
       // Check if exists
       $existingBtcpayUser = $this->userService->getUserByEmail($user->email);
       if ($existingBtcpayUser) {
           // Link existing
           $user->btcpay_user_id = $existingBtcpayUser['id'];
       } else {
           // Create new with password
           $btcpayUser = $this->userService->createUser([
               'email' => $user->email,
               'password' => $plainPassword, // From signed URL query param
           ]);
           $user->btcpay_user_id = $btcpayUser['id'];
       }
   }
   ```
4. **Create Merchant API Key**:
   ```php
   if ($user->btcpay_user_id && !$user->btcpay_api_key) {
       $apiKeyData = $this->userService->createApiKey(
           $user->btcpay_user_id,
           [], // Default permissions
           [], // All stores (no restriction)
           'satflux.io API Key - ' . $user->email
       );
       $user->btcpay_api_key = $apiKeyData['apiKey']; // Encrypted automatically
   }
   ```

**Important**: Password is passed via signed URL query parameter (encrypted) because it's needed for BTCPay user creation.

#### 3. Post-Verification State

After verification:

- `User.email_verified_at` is set
- `User.btcpay_user_id` is set (links to BTCPay Server user)
- `User.btcpay_api_key` is set (encrypted in database)

User can now create stores using their merchant API key.

---

## Store Creation & Management

### Store Creation Flow

**File**: `app/Http/Controllers/StoreController::store()`

```
1. Merchant submits store form (name, currency, timezone, wallet_type)
   ↓
2. StoreController::store()
   ├─ Validate request
   ├─ Set BtcPayClient to server-level API key
   ├─ Create store in BTCPay (server-level key)
   ├─ Add merchant as Owner (server-level key)
   ├─ Add admin as Owner (server-level key)
   ├─ Create local Store record (UUID, user_id, btcpay_store_id)
   └─ Initialize checklist items
   ↓
3. Return store data (local UUID, never btcpay_store_id)
```

**Key Points**:

1. **Store is created with server-level key** (not merchant key) - this ensures admin can be added
2. **Both merchant and admin are added as Owners** - merchant for operations, admin for support
3. **Local Store record stores mapping**: `user_id` → `btcpay_store_id`
4. **Frontend only sees local UUID** - `btcpay_store_id` is never exposed

### Store Listing (`StoreController::index`)

**Important Pattern**: Local database is source of truth for store ownership.

```php
// Get local stores first (source of truth)
$localStores = Store::where('user_id', $user->id)->get();

// Optionally merge with BTCPay API data (if merchant has API key)
if ($user->btcpay_api_key) {
    $btcpayStores = $this->storeService->listStores($user->btcpay_api_key);
    // Merge...
} else {
    // Use local stores only
}
```

**Why**: Stores created before merchant API key was provisioned still appear in list.

### Store Operations (Read/Update)

All store-scoped operations use **merchant API key**:

- `StoreController::show()` → `StoreService::getStore($btcpayStoreId, $merchantApiKey)`
- `StoreSettingsController::update()` → `StoreService::updateStore($btcpayStoreId, $data, $merchantApiKey)`
- `InvoiceService::listInvoices()` → Uses merchant API key
- `AppService::*()` → Uses merchant API key

**Authorization**: `EnsureStoreOwnership` middleware verifies `$store->user_id === $request->user()->id` before allowing access.

---

## Database Schema & Models

### Core Tables

#### `users`

- `id` (bigint, primary)
- `email` (string, nullable - for LNURL-auth users)
- `password` (hashed)
- `email_verified_at` (timestamp)
- `lightning_public_key` (string, nullable, unique)
- `btcpay_user_id` (string, nullable, indexed) - Links to BTCPay Server user
- `btcpay_api_key` (encrypted text) - Merchant's BTCPay API key
- `role` (enum: 'free', 'support', 'admin', 'pro', 'enterprise', default: 'free')

**Relationships**:

- `hasMany(Store::class)` - User owns stores

#### `stores`

- `id` (UUID, primary) - **Exposed to frontend**
- `user_id` (foreign key) - Owner merchant
- `btcpay_store_id` (string) - **Never exposed to frontend**
- `name` (string)
- `wallet_type` (enum: 'blink', 'aqua_boltz', nullable)
- `metadata` (JSON, nullable)

**Relationships**:

- `belongsTo(User::class)`
- `hasMany(StoreChecklist::class)`
- `hasMany(Export::class)`
- `hasMany(App::class)`
- `hasOne(WalletConnection::class)`

**Indexes**: `user_id`, `btcpay_store_id` (not unique - admin may have access)

#### `wallet_connections`

- `id` (UUID, primary)
- `store_id` (UUID, foreign key)
- `type` (enum: 'blink', 'aqua_descriptor')
- `encrypted_secret` (text) - Laravel encrypted (Blink token or Aqua descriptor)
- `status` (enum: 'pending', 'needs_support', 'connected')
- `submitted_by_user_id` (foreign key)
- `revealed_last_at` (timestamp, nullable)
- `revealed_last_by` (foreign key, nullable)

**Purpose**: Stores wallet connection secrets (Blink tokens, Aqua descriptors) encrypted. Support team can reveal for manual BTCPay configuration.

#### `apps`

- `id` (UUID, primary)
- `store_id` (UUID, foreign key)
- `btcpay_app_id` (string) - BTCPay app ID
- `app_type` (enum: 'PointOfSale', 'Crowdfund', 'PaymentButton', 'LightningAddress')
- `name` (string)
- `config` (JSON, nullable)
- `metadata` (JSON, nullable)

**Unique constraint**: `[store_id, btcpay_app_id]`

### Model Casts & Encryption

**User Model**:

```php
protected $casts = [
    'password' => 'hashed',
    'btcpay_api_key' => 'encrypted', // Automatic encryption/decryption
];
```

**WalletConnection Model**:

- `encrypted_secret` is stored encrypted
- Accessor: `masked_secret` (shows first 6 + last 6 chars)
- Mutator: `setSecretAttribute()` encrypts before storing
- Method: `reveal()` returns decrypted value

---

## Frontend Architecture

### Vue Application Structure

```
resources/js/
├── app.ts              # Vue app initialization
├── bootstrap.ts        # Axios setup, CSRF cookie fetch
├── router/
│   └── index.ts        # Vue Router + navigation guards
├── store/
│   ├── auth.ts         # Pinia auth store (user state)
│   └── stores.ts       # Pinia stores store (stores state)
├── services/
│   └── api.ts          # Axios instance (baseURL: '/api')
└── pages/
    ├── auth/
    ├── stores/
    └── support/
```

### State Management (Pinia)

#### Auth Store (`store/auth.ts`)

```typescript
interface User {
  id: number;
  email: string;
  email_verified_at?: string;
}

const user = ref<User | null>(null);
const isAuthenticated = computed(() => user.value !== null);

// Methods:
-fetchUser() - // GET /api/user
  login() - // POST /api/auth/login
  register() - // POST /api/auth/register
  logout(); // POST /api/auth/logout
```

**Flow**:

1. App loads → Router guard calls `fetchUser()` if `user` is null
2. `fetchUser()` makes `GET /api/user` (with cookies)
3. If 200: `user.value = response.data`
4. If 401: `user.value = null` → Router redirects to login

#### Stores Store (`store/stores.ts`)

Manages store list state. Used by dashboard and store list pages.

### Router Guards

**Location**: `resources/js/router/index.ts`

```typescript
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // Fetch user if not loaded
  if (!authStore.user) {
    await authStore.fetchUser();
  }

  // Check route requirements
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: "login" });
  } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
    next({ name: "home" });
  } else {
    next();
  }
});
```

**Route Meta**:

- `requiresAuth: true` - Requires authenticated user
- `requiresGuest: true` - Requires unauthenticated user (login/register pages)

---

## API Layer & Middleware

### Route Organization (`routes/api.php`)

```php
// Public routes (no auth)
Route::get('/health', ...);
Route::post('/webhooks/btcpay', ...);
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/auth/login', ...);
    Route::post('/auth/register', ...);
});

// Authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', ...);
    Route::get('/stores', ...);
    Route::post('/stores', ...);
    // ... all store operations
});
```

### Middleware Stack

1. **TrustProxies** (`app/Http/Middleware/TrustProxies.php`):
   - Handles X-Forwarded-\* headers (for Cloudflare/proxy)

2. **Sanctum** (via `statefulApi()`):
   - Validates session cookie
   - Sets `auth()->user()` for authenticated requests

3. **EnsureStoreOwnership** (`app/Http/Middleware/EnsureStoreOwnership.php`):

   ```php
   if ($store->user_id !== $request->user()->id) {
       abort(403);
   }
   ```

   - Applied to all store-scoped routes
   - Ensures merchant can only access their own stores

4. **EnsureSupportRole** (`app/Http/Middleware/EnsureSupportRole.php`):

   ```php
   if (!$user->isSupport() && !$user->isAdmin()) {
       abort(403);
   }
   ```

   - Applied to support-only routes (wallet connection reveal, etc.)

5. **AuditLog** (`app/Http/Middleware/AuditLog.php`):
   - Logs sensitive actions (store create, wallet connection reveal)
   - Stores in `audit_logs` table

### API Response Format

All API endpoints return JSON:

```json
{
    "data": { ... },
    "message": "Success message" // optional
}
```

Errors:

```json
{
  "message": "Error message",
  "errors": { "field": ["validation errors"] } // for 422
}
```

---

## Security Model

### Multi-Tenant Isolation

**Principle**: Stores are isolated at application layer, not database schema.

**Mechanisms**:

1. **Database-level**: `Store.user_id` foreign key ensures stores belong to users
2. **Middleware**: `EnsureStoreOwnership` verifies `$store->user_id === $request->user()->id`
3. **API Key Scoping**: Merchant API keys only access their stores (BTCPay enforces this)
4. **Frontend Filtering**: `StoreController::index()` filters by `user_id` before returning

**No Shared Data**: Stores, invoices, apps are never shared between merchants.

### API Key Security

1. **Server-level key**: Stored in `.env`, never in database
2. **Merchant keys**: Encrypted in database (`encrypted` cast), never exposed to frontend
3. **Hidden in API responses**: `User.btcpay_api_key` is in `$hidden` array
4. **Never logged**: API keys are never logged (sanitized in logs)

### UUID-Based Routing

**Problem**: Exposing `btcpay_store_id` could allow enumeration attacks.

**Solution**: Frontend only sees local UUIDs (`Store.id`). `btcpay_store_id` is never in API responses.

**Flow**:

```
Frontend: GET /api/stores/:uuid
   ↓
Middleware: EnsureStoreOwnership (checks user_id via UUID)
   ↓
Controller: Converts UUID → btcpay_store_id internally
   ↓
Service: Uses btcpay_store_id for BTCPay API calls
```

### Session Security

- **HttpOnly cookies**: `laravel_session` cannot be accessed via JavaScript
- **SameSite=Lax**: Prevents CSRF attacks
- **Secure cookies**: `SESSION_SECURE_COOKIE=true` in production (HTTPS only)
- **CSRF protection**: `XSRF-TOKEN` cookie + `X-XSRF-TOKEN` header

---

## Data Flow Diagrams

### Store Creation Flow

```
┌──────────┐
│ Merchant │
│  (Vue)   │
└────┬─────┘
     │ POST /api/stores
     │ { name, currency, wallet_type }
     ▼
┌─────────────────────┐
│ StoreController     │
│ ::store()           │
└────┬────────────────┘
     │
     ├─► Get server-level API key
     ├─► Set BtcPayClient to server key
     │
     ▼
┌─────────────────────┐
│ StoreService        │
│ ::createStore()     │
└────┬────────────────┘
     │ POST /api/v1/stores
     │ (server-level key)
     ▼
┌─────────────────────┐
│ BTCPay Server       │
│ Creates Store       │
└────┬────────────────┘
     │ Returns: { id: "btcpay_store_id" }
     ▼
┌─────────────────────┐
│ StoreController     │
│ (continues)         │
└────┬────────────────┘
     │
     ├─► addUserToStore(merchant, 'Owner')
     ├─► addUserToStore(admin, 'Owner')
     ├─► Store::create([uuid, user_id, btcpay_store_id])
     └─► Initialize checklist
     │
     ▼
┌──────────┐
│ Response │
│ { id: UUID } │
└──────────┘
```

### Store List Flow

```
┌──────────┐
│ Merchant │
│  (Vue)   │
└────┬─────┘
     │ GET /api/stores
     │ Cookie: laravel_session=...
     ▼
┌─────────────────────┐
│ Middleware          │
│ auth:sanctum        │
│ EnsureStoreOwnership│
└────┬────────────────┘
     │
     ▼
┌─────────────────────┐
│ StoreController     │
│ ::index()           │
└────┬────────────────┘
     │
     ├─► Store::where('user_id', $user->id)->get()
     │   (Local DB - source of truth)
     │
     ├─► IF $user->btcpay_api_key:
     │     StoreService::listStores($merchantKey)
     │     (BTCPay API - optional merge)
     │
     └─► Format response (only UUIDs, never btcpay_store_id)
     │
     ▼
┌──────────┐
│ Response │
│ [{ id: UUID, name, ... }] │
└──────────┘
```

### Authentication Flow (Login)

```
┌──────────┐
│ User     │
│ (Browser)│
└────┬─────┘
     │ 1. GET /sanctum/csrf-cookie
     ▼
┌─────────────────────┐
│ Sets XSRF-TOKEN     │
│ cookie              │
└────┬────────────────┘
     │
     │ 2. POST /api/auth/login
     │    { email, password }
     │    Header: X-XSRF-TOKEN
     ▼
┌─────────────────────┐
│ LoginController     │
│ ::login()           │
└────┬────────────────┘
     │
     ├─► Auth::attempt([email, password])
     │
     └─► Session created
     │
     ▼
┌─────────────────────┐
│ Response            │
│ Set-Cookie: laravel_session
│ Body: { user: {...} }
└────┬────────────────┘
     │
     ▼
┌──────────┐
│ Pinia    │
│ authStore│
│ user = {id, email}  │
└──────────┘
```

### Email Verification → BTCPay Provisioning

```
┌─────────────────────────────┐
│ User clicks email link      │
│ /auth/verify-email/{id}/{hash}?signature=...&password=...
└────┬────────────────────────┘
     │
     ▼
┌─────────────────────────────┐
│ EmailVerificationController │
│ ::verify()                  │
└────┬────────────────────────┘
     │
     ├─► Validate signed URL
     ├─► Decrypt password param
     │
     ▼
┌─────────────────────────────┐
│ DB Transaction              │
└────┬────────────────────────┘
     │
     ├─► 1. markEmailAsVerified()
     │
     ├─► 2. IF !btcpay_user_id:
     │     ├─► Check if exists on BTCPay
     │     └─► IF not exists:
     │         UserService::createUser([email, password])
     │         → Sets btcpay_user_id
     │
     └─► 3. IF !btcpay_api_key:
         │   UserService::createApiKey(btcpay_user_id, permissions)
         │   → Sets btcpay_api_key (encrypted)
         │
         ▼
┌─────────────────────────────┐
│ User Provisioned            │
│ - email_verified_at: set    │
│ - btcpay_user_id: set       │
│ - btcpay_api_key: set       │
└─────────────────────────────┘
```

---

## Key Implementation Details

### Cache Key Isolation

When caching BTCPay API responses, cache keys include API key hash to prevent cross-merchant cache pollution:

```php
$apiKeyHash = $userApiKey ? md5($userApiKey) : 'server';
$cacheKey = "btcpay:store:{$storeId}:{$apiKeyHash}";
```

**Why**: Different merchants may have different views of the same store (if they're both owners).

### Error Handling Pattern

```php
try {
    $result = $service->method();
} catch (BtcPayException $e) {
    Log::error('Operation failed', ['error' => $e->getMessage()]);
    // Don't expose internal errors to frontend
    abort(500, 'Operation failed. Please try again.');
}
```

**User-Facing Errors**: Generic messages (security)
**Logs**: Detailed errors with context (debugging)

### Idempotency

Several operations are idempotent:

- **Email verification**: Can be called multiple times (checks `email_verified_at`)
- **BTCPay user creation**: Checks `getUserByEmail()` before creating
- **Store user assignment**: Can be called multiple times (BTCPay handles duplicates)

### Transaction Boundaries

Critical operations use database transactions:

- Store creation (create BTCPay store + local record + checklist)
- Email verification (verify email + create BTCPay user + create API key)

**Why**: Ensures consistency - if BTCPay API call fails, local DB is rolled back.

---

## Frontend-Backend Communication

### API Endpoint Naming

All endpoints use RESTful conventions:

- `GET /api/stores` - List stores
- `POST /api/stores` - Create store
- `GET /api/stores/{uuid}` - Get store (UUID from route binding)
- `PUT /api/stores/{uuid}/settings` - Update store settings

**Route Model Binding**: Laravel automatically resolves `{store}` parameter to `Store` model via UUID.

### Request Headers

All authenticated requests include:

- `Cookie: laravel_session=...` (automatic, browser sends)
- `X-XSRF-TOKEN: ...` (from `XSRF-TOKEN` cookie, Axios adds as header)
- `Accept: application/json`
- `Content-Type: application/json`

### Response Handling

**Success** (200/201):

```typescript
{
    data: { ... },
    message?: string
}
```

**Error** (4xx/5xx):

```typescript
{
    message: string,
    errors?: { field: string[] } // for validation errors
}
```

**Axios Interceptor** (`resources/js/services/api.ts`):

- 401 → Redirect to `/login`
- Other errors → Reject promise (component handles)

---

## Wallet Connections

**Purpose**: Merchants provide wallet connection strings (Blink tokens, Aqua descriptors) which are encrypted and stored. Support team reveals them for manual BTCPay configuration.

### Flow

1. **Merchant submits** (`POST /api/stores/{uuid}/wallet-connection`):
   - Validates connection string format
   - Encrypts and stores in `wallet_connections.encrypted_secret`
   - Status: `needs_support`

2. **Support reveals** (`POST /api/support/wallet-connections/{id}/reveal`):
   - Requires password/2FA confirmation
   - Returns decrypted value (temporary display)
   - Logs audit entry

3. **Support configures** in BTCPay UI (manual, not automated)

4. **Support marks connected** (`PUT /api/support/wallet-connections/{id}/mark-connected`):
   - Status: `connected`

**Security**:

- Secrets encrypted at rest (Laravel encryption)
- Only support/admin can reveal
- Audit log tracks all reveals
- Masked display for merchants (first 6 + last 6 chars)

---

## Webhooks

**Endpoint**: `POST /api/webhooks/btcpay`

**Flow**:

1. BTCPay Server sends webhook (invoice update, store update, etc.)
2. `WebhookController::handle()` verifies signature (if `BTCPAY_WEBHOOK_SECRET` set)
3. Stores event in `webhook_events` table
4. Dispatches `ProcessBtcPayWebhook` job (async processing)

**Current State**: Job exists but processing logic is minimal (stores events for audit).

---

## File Structure Reference

### Backend

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/              # Authentication controllers
│   │   ├── Store*.php         # Store management
│   │   ├── WalletConnectionController.php
│   │   ├── ExportController.php
│   │   └── AppController.php
│   └── Middleware/
│       ├── EnsureStoreOwnership.php
│       ├── EnsureSupportRole.php
│       └── AuditLog.php
├── Models/                     # Eloquent models
├── Services/
│   ├── BtcPay/                # BTCPay API integration
│   ├── WalletConnectionService.php
│   └── StoreChecklistService.php
└── Jobs/
    └── GenerateCsvExport.php
```

### Frontend

```
resources/js/
├── pages/
│   ├── auth/                  # Login, Register, VerifyEmail
│   ├── stores/                # Store management pages
│   └── support/               # Support-only pages
├── components/
│   ├── stores/                # Reusable store components
│   └── support/               # Support components
├── store/                     # Pinia stores
├── services/
│   └── api.ts                 # Axios configuration
└── router/
    └── index.ts               # Vue Router + guards
```

---

## Environment Variables

### Required for Local Development

```env
# Application
APP_URL=http://localhost:8080
APP_ENV=local

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=panel
DB_USERNAME=panel
DB_PASSWORD=panel

# BTCPay Server
BTCPAY_BASE_URL=https://satflux.org
BTCPAY_API_KEY=your_server_level_key

# Sanctum & Session (for localhost)
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,127.0.0.1,127.0.0.1:8080
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

### Production

```env
APP_URL=https://satflux.io
SESSION_DOMAIN=satflux.io
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=satflux.io
```

---

## Common Patterns & Conventions

### Service Layer Pattern

Business logic is in Services, not Controllers:

```php
// Controller (thin)
public function store(StoreCreateRequest $request)
{
    $store = $this->storeService->create(...);
    return response()->json(['data' => $store]);
}

// Service (thick)
class StoreService {
    public function create(...) {
        // All business logic here
    }
}
```

### Token Override Pattern

All BTCPay service methods accept optional `$userApiKey`:

```php
public function getStore(string $storeId, ?string $userApiKey = null): array
{
    // Temporarily switch API key if provided
    // Make API call
    // Restore original API key
}
```

### Model Relationships

Always eager load relationships when needed:

```php
Store::where('user_id', $user->id)
    ->with(['checklistItems', 'user'])
    ->get();
```

### Error Logging

Always log errors with context:

```php
Log::error('Operation failed', [
    'user_id' => $user->id,
    'store_id' => $store->id,
    'error' => $e->getMessage(),
]);
```

**Never log**: API keys, passwords, sensitive user data.

---

## Testing Considerations

### Unit Tests

- Test services in isolation (mock BtcPayClient)
- Test models (relationships, casts, accessors)

### Feature Tests

- Test authentication flows
- Test store creation (use `Http::fake()` for BTCPay API)
- Test authorization (users cannot access other users' stores)

### Integration Tests

- Test BTCPay API integration (may require real BTCPay instance or extensive mocking)

---

## Troubleshooting Guide

### "Store not found" after creation

**Check**:

1. `StoreController::index()` returns stores from local DB (`Store::where('user_id', ...)`)
2. Verify `stores.user_id` matches authenticated user
3. Check `stores` table directly: `SELECT * FROM stores WHERE user_id = ?`

### "BTCPay API key not configured"

**Check**:

1. User has `btcpay_api_key` set: `SELECT btcpay_api_key FROM users WHERE id = ?`
2. If null, email verification may not have completed API key creation
3. Check logs for API key creation errors

### "Logout on refresh"

**Check**:

1. `SANCTUM_STATEFUL_DOMAINS` includes current domain
2. `SESSION_DOMAIN` is empty or matches domain
3. Cookies are being set (DevTools → Application → Cookies)
4. `laravel_session` cookie has correct domain/path

### "Cannot create store"

**Check**:

1. User has `btcpay_api_key` (or store creation uses server-level key)
2. `BTCPAY_API_KEY` in `.env` is valid
3. BTCPay Server is accessible
4. Check logs for BTCPay API errors

---

## Summary: Critical Understanding Points

1. **Dual API Keys**: Server-level (provisioning) vs Merchant-level (operations)
2. **Local DB is source of truth** for store ownership (not BTCPay API)
3. **UUIDs hide BTCPay IDs** from frontend (security)
4. **Sanctum SPA cookies** (not tokens) - requires `withCredentials: true`
5. **Email verification triggers** BTCPay user + API key creation
6. **Store creation uses server-level key**, then adds merchant + admin as owners
7. **All store operations use merchant key** (after provisioning)
8. **Multi-tenant isolation** via `EnsureStoreOwnership` middleware + DB filtering

---

This document should serve as a comprehensive reference for understanding how all components interact. For specific implementation details, refer to the code files mentioned throughout.
