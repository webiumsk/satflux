# NWC Connector

Receive-only Nostr Wallet Connect (NIP-47) connector service. Bridges BTCPay Server with Lightning backends (LND, NWC wallets) with strict receive-only policy.

- **Allowed:** `make_invoice`, `lookup_invoice`
- **Denied:** `pay_invoice` and any send methods

Runs as a separate process in the same deployment as the Merchant Panel (see root `docker-compose.yml`).

## Build (Docker)

```bash
docker compose build nwc-connector
```

## Run (with Panel)

```bash
# From repo root
docker compose up -d postgres
docker compose run --rm php php artisan migrate
docker compose up -d nwc-connector
```

See **[docs/TESTING-NWC.md](../docs/TESTING-NWC.md)** in the repo root for full testing steps (env, health checks, creating a connector via CLI or API).

## Environment

- `NWC_HTTP_ADDR` – listen address (default `:8082`)
- `DATABASE_URL` – PostgreSQL (same DB as Panel)
- `NWC_RELAY_URL` – Nostr relay (default `wss://relay.getalby.com/v1`)
- `NWC_MASTER_KEY` – 32-byte key (or 64 hex chars) for encrypting connector secrets in DB; **required** for creating connectors
- `NWC_PANEL_API_KEY` – optional; if set, Panel requests should send `Authorization: Bearer <key>`

## API (internal; called by Panel)

- `POST /connectors` – create connector (body: `store_id`, `btcpay_store_id`, optional `backend_nwc_uri`). If `backend_nwc_uri` is provided (merchant’s NWC URI from Alby/Phoenix etc.), the connector stores it encrypted and forwards `make_invoice`/`lookup_invoice` to the merchant wallet (receive-only). Returns `connector_id`, `connection_string`, `nwc_uri`.
- `GET /connectors/{id}` – get connector info (masked)
- `POST /connectors/{id}/revoke` – revoke connector
- `GET /health` – health check

## BTCPay connection format

Nostr plugin expects:

```
type=nwc;key=nostr+walletconnect:<pubkey_hex>?relay=wss%3A%2F%2F<relay_host>&secret=<secret_hex>
```

The Panel sets this in BTCPay Lightning settings after creating a connector.

## MVP status

- HTTP API and DB schema: done
- Nostr listener: subscribes to relay for kind 23194 (#p = our pubkeys), decrypts NIP-04, policy check (make_invoice, lookup_invoice, get_info only), adapter call, publishes kind 23195 response
- Policy: strict whitelist (no pay_invoice or any send method)
- Wallet adapter: **NWC client adapter** – when connector has `backend_type=nwc` and encrypted merchant NWC URI, forwards `make_invoice` and `lookup_invoice` to the merchant’s wallet via NIP-47 (receive-only; no custody)
