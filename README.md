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
   - BTCPay Server API configuration
   - Session and Sanctum settings

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

## Development

- **Backend API**: http://localhost:8080/api
- **Frontend**: http://localhost:8080
- **Database**: localhost:5432
- **Redis**: localhost:6379

## Production Deployment

The application is configured to run behind Cloudflare with:
- TrustProxies middleware configured for proxy headers
- Secure session cookies
- Sanctum SPA cookie authentication
- Rate limiting on auth endpoints

Ensure the following environment variables are set in production:
- `APP_URL=https://panel.dvadsatjeden.org`
- `SESSION_DOMAIN=panel.dvadsatjeden.org`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- `SANCTUM_STATEFUL_DOMAINS=panel.dvadsatjeden.org`

## License

MIT

