# Testing NWC Connector

Steps to get the NWC Connector into a testable state locally.

## 1. Environment

Copy `.env.example` to `.env` if you haven't already. Set at least:

```bash
# Required for connector creation (32 bytes as 64 hex chars). Generate:
#   openssl rand -hex 32
NWC_MASTER_KEY=your_64_char_hex_key_here
```

DB must match between Laravel and nwc-connector (same `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`; docker-compose passes them to both).

## 2. Start services

```bash
docker compose up -d postgres redis
docker compose run --rm php php artisan migrate
docker compose up -d php nginx nwc-connector
```

Or start everything:

```bash
docker compose up -d
# then run migrations if DB was fresh:
docker compose run --rm php php artisan migrate
```

## 3. Health checks

- **NWC Connector direct:** `curl -s http://localhost:8082/health` → `{"status":"ok"}`
- **Via Panel API:** `curl -s http://localhost:8080/api/health/nwc-connector` → `{"status":"ok"}` or `{"status":"unavailable"}` if connector is down

## 4. Create a connector (CLI, no UI)

You need a store (e.g. create one via Panel or seed). Then:

```bash
docker compose exec php php artisan nwc:create-connector <store_uuid>
```

Example (replace with a real store UUID from your DB):

```bash
docker compose exec php php artisan nwc:create-connector 550e8400-e29b-41d4-a716-446655440000
```

Output: connector ID and BTCPay connection string (`type=nwc;key=...`). You can paste that into BTCPay Server → Store → Lightning → Add connection (with Nostr plugin).

## 5. Create a connector via API (with auth)

1. Log in via Panel (or get a Sanctum token).
2. Get a store ID (UUID) from `/api/stores` or the app.
3. POST create connector:

```bash
curl -s -X POST http://localhost:8080/api/stores/YOUR_STORE_UUID/nwc-connector \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Cookie: your_session_cookie" \
  -d '{}'
```

(Replace `YOUR_STORE_UUID` and use the same session/cookie as in the browser, or use a Bearer token if using API token auth.)

Response: `connector_id`, `connection_string`, `nwc_uri`. Use `connection_string` in BTCPay Lightning settings.

## 6. Optional: test Nostr path

With a connector created and BTCPay (with Nostr plugin) using the NWC connection string, creating an invoice in BTCPay will send a NIP-47 request to the relay; the connector will receive it and respond (currently via stub adapter: “lightning backend not configured”). To see real invoices, implement the LND/NWC adapter in the connector.

## Troubleshooting

- **"Permission denied" on storage/logs:** The PHP container runs as `www-data` but the mounted `storage` has host ownership. Either run on the host: `chmod -R 775 storage bootstrap/cache` (and if needed `chgrp -R 33 storage bootstrap/cache` so group matches www-data), or rebuild the PHP image: the Dockerfile entrypoint now fixes storage/cache permissions on container start.
- **Connector won't start:** Ensure `NWC_MASTER_KEY` is set (64 hex chars). Check logs: `docker compose logs nwc-connector`.
- **Panel can't reach connector:** Ensure both are on the same docker network and `NWC_CONNECTOR_URL=http://nwc-connector:8082` in `.env`.
- **Migrations:** Run `docker compose run --rm php php artisan migrate` so `nwc_connectors` and `stores.nwc_connector_id` exist.
- **DB mismatch:** `.env` `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` are used by Laravel and by docker-compose for postgres and nwc-connector; keep them in sync.
