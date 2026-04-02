# satflux.io - standalone production deployment

Single Docker stack with **Caddy** as the reverse proxy and automatic HTTPS (Let’s Encrypt). For day-to-day development, use `docker-compose.yml` and [README.md](../README.md).

## Requirements

- A VPS with Docker and Docker Compose
- Git access to the repository
- A DNS **A** record pointing your domain at the server (for HTTPS)

## Quick start

1. **Clone** (example path `/opt/satflux`):

   ```bash
   git clone <repository-url> /opt/satflux
   cd /opt/satflux
   ```

2. **Environment** - copy from `.env.example` to `.env.standalone` and set at least:
   - `APP_KEY` (`php artisan key:generate`)
   - `APP_URL` (e.g. `https://satflux.io`)
   - `POSTGRES_PASSWORD` and matching `DB_PASSWORD`
   - `SITE_ADDRESS=satflux.io` (or your hostname)
   - `ACME_EMAIL=you@example.com`
   - `STANDALONE_HTTP_PORT=80`, `STANDALONE_HTTPS_PORT=443`
   - Redis, mail, `BTCPAY_*`, `SANCTUM_STATEFUL_DOMAINS`, and session settings as needed

3. **Deploy:**

   ```bash
   ./deploy.sh
   ```

   The script uses `deploy.config.sh` and `.env.standalone` by default. For manual Compose:

   ```bash
   docker compose -f docker-compose.standalone.yml --env-file .env.standalone --project-name satflux_standalone up -d
   docker compose -f docker-compose.standalone.yml --env-file .env.standalone --project-name satflux_standalone logs -f
   ```

## Modes

- **HTTPS (production):** `SITE_ADDRESS=satflux.io`, ports **80** and **443**. Port 80 must be reachable from the internet for ACME HTTP-01.
- **HTTP only (lab):** e.g. `SITE_ADDRESS=localhost:80` and map `STANDALONE_HTTP_PORT=8090`.
- **Another proxy on 80/443:** map e.g. `8090:80` / `8443:443`; terminating TLS elsewhere may require extra Caddy or proxy configuration.

## Containers (typical names)

| Container                      | Role                          |
| ------------------------------ | ----------------------------- |
| `satflux_caddy_standalone`     | Caddy (TLS, reverse proxy)    |
| `satflux_nginx_standalone`     | Nginx (static + PHP upstream) |
| `satflux_php_standalone`       | PHP-FPM (Laravel)             |
| `satflux_reverb_standalone`    | Laravel Reverb (WebSockets)   |
| `satflux_queue_standalone`     | Queue worker                  |
| `satflux_scheduler_standalone` | Scheduler                     |
| `satflux_postgres_standalone`  | PostgreSQL                    |
| `satflux_redis_standalone`     | Redis                         |

## Backup and restore

- **Config:** copy `backup.config.example.sh` to `backup.config.sh` (ignored by git) and set paths, containers, and optional S3 upload.
- **Backup:** `./backup.sh` (sources `backup.config.sh` when present; optional S3 upload).
- **Restore:** `./restore.sh` and pick metadata under `backups/metadata/`.

## Production checklist

- Reverse proxy / CDN (e.g. Cloudflare): enable `TrustProxies` and correct `X-Forwarded-*` headers.
- `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, and `SANCTUM_STATEFUL_DOMAINS` must match the hostname users load in the browser.
- Run `php artisan migrate` inside the PHP container after deploy; keep `APP_DEBUG=false`.
- After any `.env` change: `php artisan optimize:clear`.

## Troubleshooting

- **Let’s Encrypt fails:** Confirm public DNS, open firewall for 80/443, and that nothing else binds those ports unexpectedly.
- **Compose warnings about variables:** Always pass `--env-file .env.standalone` when running `docker compose` manually for this stack.
- **`storage/framework/views` permission denied (Blade compile):** The bind-mounted `storage` (and `bootstrap/cache`) must be writable by PHP-FPM (`www-data` in the official image). After fixing ownership on the host (e.g. `chown` to your user), reset for Docker:

  ```bash
  docker compose -f docker-compose.standalone.yml --env-file .env.standalone exec -u root php \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
  ```

  For the dev stack (`docker-compose.yml`), use the same `chown` against the `php` service. On the host you can instead run `sudo chown -R 33:33 storage bootstrap/cache` if UID/GID `33` matches `www-data` in the PHP image.
