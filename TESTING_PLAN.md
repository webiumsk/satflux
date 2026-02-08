# satflux.io - Testing Plan

## Current Test Coverage

**Backend (PHP/Laravel):**

- ✅ `tests/Feature/AuthTest.php` - Basic authentication (register, login)
- ✅ `tests/Feature/StoreAuthorizationTest.php` - Store access authorization

**Frontend (Vue.js/TypeScript):**

- ❌ No tests currently

---

## Proposed Test Coverage

### 1. Backend Feature Tests (PHPUnit)

#### 1.1 Authentication & Authorization

**File: `tests/Feature/AuthTest.php` (extend existing)**

- [x] User can register
- [x] User can login
- [ ] User cannot login with invalid credentials
- [ ] User can logout
- [ ] Email verification flow
- [ ] LNURL-Auth authentication flow
- [ ] Password reset flow

#### 1.2 Store Management

**File: `tests/Feature/StoreTest.php` (new)**

- [ ] User can create store
- [ ] User can view own stores
- [ ] User can update own store settings
- [ ] User can delete own store (local only)
- [ ] Store settings include timezone, currency, preferred_exchange
- [ ] Store logo upload/delete
- [ ] User cannot access other user's stores
- [ ] Store list filtering

#### 1.3 App Management

**File: `tests/Feature/AppTest.php` (new)**

- [ ] User can create PoS app
- [ ] User can create Crowdfund app
- [ ] User can create Payment Button app
- [ ] User can create LN Address app
- [ ] User can update app settings
- [ ] User can delete app
- [ ] App creation with default currency from store
- [ ] App cannot be accessed by other users

#### 1.4 Wallet Connections

**File: `tests/Feature/WalletConnectionTest.php` (new)**

- [ ] User can submit wallet connection string
- [ ] Blink connection string validation
- [ ] Boltz descriptor validation
- [ ] Invalid connection string rejection
- [ ] Connection status updates
- [ ] Support can reveal connection secret (with password)
- [ ] Support can mark connection as connected
- [ ] Email notification sent when connection ready

#### 1.5 Dashboard & Statistics

**File: `tests/Feature/DashboardTest.php` (new)**

- [ ] Dashboard shows store statistics
- [ ] Sales over time calculation (7d, 30d)
- [ ] Top items calculation
- [ ] Dashboard only accessible to store owner

#### 1.6 Support Features

**File: `tests/Feature/SupportTest.php` (new)**

- [ ] Support can list wallet connections needing support
- [ ] Support can filter by status
- [ ] Support can search connections
- [ ] Support can reveal secret with password
- [ ] Regular users cannot access support endpoints
- [ ] Admin users can access support endpoints

#### 1.7 Audit Logging

**File: `tests/Feature/AuditLogTest.php` (new)**

- [ ] Store creation is logged
- [ ] Store deletion is logged
- [ ] App creation is logged
- [ ] App deletion is logged
- [ ] Wallet connection configuration is logged

#### 1.8 API Integration (BTCPay)

**File: `tests/Feature/BtcPayIntegrationTest.php` (new)**

- [ ] Mock BTCPay API responses
- [ ] Store creation in BTCPay
- [ ] App creation in BTCPay
- [ ] Error handling for BTCPay API failures
- [ ] API key creation for users

---

### 2. Backend Unit Tests (PHPUnit)

#### 2.1 Services

**Directory: `tests/Unit/Services/`**

**`WalletConnectionValidatorTest.php`**

- [ ] Valid Blink connection string parsing
- [ ] Invalid Blink connection string rejection
- [ ] Valid Boltz descriptor parsing
- [ ] Invalid Boltz descriptor rejection
- [ ] Connection string type detection

**`WalletConnectionServiceTest.php`**

- [ ] Connection status update logic
- [ ] Email notification dispatch on status change
- [ ] Connection encryption/decryption

**`StoreChecklistServiceTest.php`**

- [ ] Checklist initialization
- [ ] Checklist item updates

#### 2.2 Request Validation

**Directory: `tests/Unit/Requests/`**

**`StoreCreateRequestTest.php`**

- [ ] Required fields validation
- [ ] Currency format validation
- [ ] Timezone validation
- [ ] Wallet type validation
- [ ] Connection string validation

**`StoreUpdateRequestTest.php`**

- [ ] Field validation
- [ ] Optional fields handling

**`AppCreateRequestTest.php`**

- [ ] App name validation
- [ ] App type validation
- [ ] Config validation

---

### 3. Frontend Unit Tests (Vitest)

#### 3.1 Setup

**Add to `package.json`:**

```json
{
  "devDependencies": {
    "@vue/test-utils": "^2.4.0",
    "vitest": "^1.0.0",
    "jsdom": "^23.0.0"
  },
  "scripts": {
    "test": "vitest",
    "test:ui": "vitest --ui"
  }
}
```

**Create `vitest.config.ts`:**

```typescript
import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: "jsdom",
    setupFiles: ["./tests/setup.ts"],
  },
});
```

#### 3.2 Store Tests

**Directory: `tests/unit/stores/`**

**`stores.spec.ts`**

- [ ] Store store (Pinia) actions
- [ ] Store fetching
- [ ] Store creation
- [ ] Store update
- [ ] Store deletion
- [ ] Dashboard data fetching

#### 3.3 Component Tests

**Directory: `tests/unit/components/`**

**`StoreSidebar.spec.ts`**

- [ ] Store list rendering
- [ ] App list rendering
- [ ] Wallet connection status display
- [ ] Create app button click
- [ ] Settings button click

**`WalletConnectionForm.spec.ts`**

- [ ] Connection string input
- [ ] Validation errors display
- [ ] Test connection button
- [ ] Form submission

**`RevealSecretModal.spec.ts`**

- [ ] Password input
- [ ] Secret reveal
- [ ] Copy to clipboard
- [ ] Auto-hide countdown

#### 3.4 Page Tests

**Directory: `tests/unit/pages/`**

**`Dashboard.spec.ts`**

- [ ] Store list rendering
- [ ] Empty state
- [ ] Wallet connection status display
- [ ] Logo display

**`Show.spec.ts` (Store Detail)**

- [ ] Store info display
- [ ] Settings form
- [ ] Logo upload/delete
- [ ] Store deletion modal
- [ ] Dashboard statistics display

**`AppsShow.spec.ts`**

- [ ] App settings form
- [ ] Default view radio buttons
- [ ] Currency datalist
- [ ] Form submission
- [ ] App deletion

---

### 4. Integration Tests (E2E - Optional)

**Framework: Playwright or Cypress**

**Scenarios:**

1. **User Registration & Store Creation**
   - User registers
   - User creates store
   - User configures wallet connection
   - User creates PoS app

2. **Support Workflow**
   - Support logs in
   - Support views wallet connections
   - Support reveals secret
   - Support configures in BTCPay
   - Support marks as connected
   - Merchant receives email notification

3. **Store Management**
   - User updates store settings
   - User uploads logo
   - User deletes store

---

## Implementation Priority

### Phase 1: Critical Backend Tests (Week 1)

1. ✅ Extend `AuthTest.php`
2. ✅ Create `StoreTest.php`
3. ✅ Create `AppTest.php`
4. ✅ Create `WalletConnectionTest.php`

### Phase 2: Backend Service Tests (Week 2)

1. ✅ Create `WalletConnectionValidatorTest.php`
2. ✅ Create `WalletConnectionServiceTest.php`
3. ✅ Create request validation tests

### Phase 3: Frontend Tests Setup (Week 3)

1. ✅ Setup Vitest
2. ✅ Create store tests (Pinia)
3. ✅ Create basic component tests

### Phase 4: Frontend Component Tests (Week 4)

1. ✅ Test all major components
2. ✅ Test page components
3. ✅ Test forms and validation

---

## Running Tests

### Backend

```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/StoreTest.php

# With coverage
php artisan test --coverage
```

### Frontend

```bash
# All tests
npm run test

# Watch mode
npm run test -- --watch

# UI mode
npm run test:ui
```

---

## Test Coverage Goals

- **Backend:**
  - Feature tests: 80%+ coverage
  - Unit tests: 70%+ coverage
  - Critical paths: 100% coverage

- **Frontend:**
  - Store/Pinia logic: 80%+ coverage
  - Components: 60%+ coverage
  - Forms: 90%+ coverage

---

## Mocking Strategy

### Backend

- Use `Http::fake()` for BTCPay API calls
- Use factories for model creation
- Use `Event::fake()` for testing notifications

### Frontend

- Mock API calls with `vi.mock()`
- Mock Pinia stores
- Mock router navigation

---

## Continuous Integration

Add to `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
      - run: composer install
      - run: php artisan test

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
      - run: npm install
      - run: npm run test
```

---

## Notes

- **BTCPay API Mocking**: Since we integrate with BTCPay Server, we should mock all external API calls in tests
- **Database**: Use SQLite in-memory database for faster tests
- **Authentication**: Use Laravel's testing helpers for authenticated requests
- **Frontend Testing**: Focus on logic and user interactions, not styling
