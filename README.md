# D21 Panel - BTCPay Server Control Panel

A production-ready multi-tenant control panel for managing BTCPay Server stores via Greenfield API.

## Tech Stack

- **Backend**: Laravel 11 (PHP 8.3), Sanctum, PostgreSQL, Redis
- **Frontend**: Vue 3 + TypeScript + Vite + TailwindCSS
- **Infrastructure**: Docker Compose (nginx + php-fpm + postgres + redis)

## Prerequisites

- Docker and Docker Compose
- Node.js 18+ and npm (for local frontend development)

## Setup

1. **Clone the repository**

2. **Copy environment file**:
   ```bash
   cp .env.example .env
   ```

3. **Configure environment variables** in `.env`:
   - Database credentials
   - BTCPay Server API configuration (see BTCPay API Key Permissions below)
   - Session and Sanctum settings:
     ```
     # Sanctum SPA authentication (for localhost development)
     SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,127.0.0.1,127.0.0.1:8080
     
     # Session configuration for localhost (HTTP, not HTTPS)
     SESSION_DOMAIN=
     SESSION_SECURE_COOKIE=false
     SESSION_SAME_SITE=lax
     ```

4. **Start Docker containers**:
   ```bash
   docker-compose up -d
   ```

5. **Install PHP dependencies**:
   ```bash
   docker-compose exec php composer install
   ```

6. **Generate application key**:
   ```bash
   docker-compose exec php php artisan key:generate
   ```

7. **Run migrations**:
   ```bash
   docker-compose exec php php artisan migrate
   ```

8. **Install Node dependencies**:
   ```bash
   npm install
   ```

9. **Build frontend assets** (development):
   ```bash
   npm run dev
   ```

   Or build for production:
   ```bash
   npm run build
   ```

10. **Clear configuration cache** (if you modify `.env` or config files):
   ```bash
   docker-compose exec php php artisan optimize:clear
   ```
   
   This is especially important for local troubleshooting when changing session or Sanctum settings.

## Development

- **Backend API**: http://localhost:8080/api
- **Frontend**: http://localhost:8080
- **Database**: localhost:5432
- **Redis**: localhost:6379

## Production Deployment

Pre detailný návod na nasadenie aplikácie na produkčný server pozri [DEPLOYMENT.md](DEPLOYMENT.md).

Aplikácia je nakonfigurovaná na beh za Cloudflare proxy s:
- TrustProxies middleware pre proxy headers
- Secure session cookies
- Sanctum SPA cookie authentication
- Rate limiting na auth endpoints

Kľúčové environment variables pre produkciu:
- `APP_URL=https://panel.dvadsatjeden.org`
- `SESSION_DOMAIN=panel.dvadsatjeden.org`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- `SANCTUM_STATEFUL_DOMAINS=panel.dvadsatjeden.org`
- `LNURL_AUTH_DOMAIN=https://panel.dvadsatjeden.org`

## BTCPay API Key Permissions

The server-level API key (`BTCPAY_API_KEY`) should have the following permissions:
- **Store Management**: Create stores, read/store update (safe fields only)
- **Invoice Management**: List and read invoices
- **Webhook Access**: Receive webhook events (if webhooks are configured)

The API key is stored server-side only and all store access is enforced at the application layer via the `stores` table mapping `user_id` to `btcpay_store_id`.

## Store Creation Flow

1. User completes 3-step wizard:
   - Step 1: Store name, default currency, timezone
   - Step 2: Choose wallet backend (Blink or Aqua via Boltz plugin)
   - Step 3: Confirmation

2. System creates store in BTCPay Server via Greenfield API

3. Local store record created with UUID (never expose `btcpay_store_id` to frontend)

4. Wallet onboarding checklist initialized based on `wallet_type`

5. User redirected to "Next steps" page with checklist link

## Wallet Onboarding Checklist

After store creation, merchants complete a dynamic checklist based on their chosen wallet type:

### Blink Checklist:
1. Connect Blink wallet in BTCPay Store → Wallet settings
2. Confirm Lightning payments enabled
3. Test invoice payment (link to test invoice)
4. Optional: Set payout/withdrawal policy in Blink

### Aqua (Boltz) Checklist:
1. Open store in BTCPay
2. Enable Boltz plugin for the store
3. Connect Aqua wallet via Boltz
4. Verify swap routing works
5. Test Lightning invoice payment

The checklist is informational only and does not validate wallet state automatically. Checklist items can be marked as complete manually.

## Export Formats

The system provides two CSV export formats:

### Standard CSV Format
- Columns: invoiceId, createdTime, status, amount, currency, paidAmount, paymentMethod, buyerEmail, orderId, checkoutLink
- Suitable for general invoice management

### Accounting-Friendly CSV Format
- Columns: invoice_id, issue_date, settlement_date, gross_amount, currency, payment_method, status, external_reference
- Snake_case column names
- Date formatting (YYYY-MM-DD)
- No Lightning-specific internals

Exports support filtering by date range and status. Large exports use pagination and streaming to handle datasets of any size safely.

## LNURL-Auth Skeleton

The application includes a skeleton implementation of LNURL-auth:

- Challenge endpoint: `POST /api/lnurl-auth/challenge` (generates k1, returns lnurl-auth URL)
- Verification endpoint: `POST /api/lnurl-auth/verify` (exists but verification logic is TODO)

When `LNURL_AUTH_ENABLED=false`, the "Login with Lightning" button is hidden. When enabled, the button is shown but verification is not yet implemented.

Classic email/password authentication always works regardless of LNURL-auth status.

## Security Features

- **Multi-tenant isolation**: Store access enforced via `stores` table mapping
- **UUID-based routing**: Only local UUIDs exposed to frontend, never `btcpay_store_id`
- **Audit logging**: Sensitive actions (store create/update, exports) are logged
- **Rate limiting**: Auth endpoints rate limited
- **Webhook verification**: Webhook signature verification when `BTCPAY_WEBHOOK_SECRET` is set
- **CSRF protection**: Sanctum SPA cookie authentication with CSRF tokens
- **Secure cookies**: HttpOnly, secure, SameSite=Lax

## Testing

Run tests:
```bash
docker-compose exec php php artisan test
```

Key test suites:
- Authentication flows
- Store creation (uses Http::fake() for BTCPay)
- Authorization (users cannot access other users' stores)
- Export jobs

## License

MIT
