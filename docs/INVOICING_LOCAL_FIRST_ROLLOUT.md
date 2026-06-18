# Local-first invoicing - production rollout

Enable Evolu-based invoicing (companies, contacts, documents, expenses in the browser) on satflux.io production. Pair this guide with [DEPLOYMENT.md](DEPLOYMENT.md) and [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md).

## What changes

| Layer                 | Flag                              | Effect                                                                                                          |
| --------------------- | --------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Frontend (build-time) | `VITE_INVOICING_LOCAL_FIRST=true` | SPA reads/writes invoicing via Evolu SQLite + E2EE relay; server invoicing CRUD APIs are bypassed for core data |
| Backend (runtime)     | `INVOICING_LOCAL_FIRST=true`      | `Company::usesServerInvoicing()` returns false globally; WooCommerce inbox routing uses local-first paths       |

Both flags should be **on together** in production. Mismatch causes confusing behaviour (e.g. WooCommerce hooks still writing server rows while the UI shows Evolu).

Optional:

- `VITE_EVOLU_RELAY_URL` - primary WebSocket relay (build-time). Production: `wss://relay.satflux.io` (self-hosted).
- `VITE_EVOLU_RELAY_URL_SECONDARY` - secondary relay for resilience (default `wss://free.evoluhq.com` when unset). Set to empty string to disable.
- `RELAY_SITE_ADDRESS` - Caddy TLS hostname for the Evolu relay container in the standalone stack (e.g. `relay.satflux.io`). `deploy.sh` writes `docker/caddy/Caddyfile.relay` from this value.
- Empty primary + empty secondary = local-only SQLite (no multi-device sync).
- `SEED_FIRST_REGISTRATION=true` - recommended with local-first (unified 24-word recovery phrase)
- Per-company override: `app_settings.local_first` on a `companies` row forces that company off server invoicing even when the global flag is false (legacy escape hatch)

## Before you flip the switch

### 1. Existing server-side invoicing data

The frontend flag is **global** (baked into the Vite bundle). There is no per-user runtime toggle.

Users who already have companies and documents **only in PostgreSQL** will see an **empty** Invoicing module after rollout until they:

- use **Import from server** on `/invoicing` (one-time PG → Evolu import in the browser), or
- restore the same Satflux recovery phrase on a device that already had Evolu data, or
- create new local companies from scratch.

Server rows are **not deleted** by the import. After verifying local data, users can remove legacy companies under **Profile → Server companies**.

### Migration API (Pro, authenticated)

| Endpoint                                          | Purpose                                                                                                                              |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `GET /api/invoicing/migration/status`             | Counts of server-side companies/contacts/documents/expenses and expense attachments                                                  |
| `GET /api/invoicing/migration/export`             | Full `InvoicingDataSnapshot` JSON for browser `restoreInvoicingSnapshot` (throttled 5/hour; attachments and branding off by default) |
| `GET /api/invoicing/migration/export-attachments` | Phase 2: `expenseAttachment` rows with `contentBase64` when readable (max ~384 KB each; throttled 3/hour)                            |

CLI for support: `php artisan invoicing:export-for-evolu {email}` writes JSON to `storage/app/private/`.

### 2. Plan and onboarding model

- **Guest** - no Business Invoicing (BTCPay demo only).
- **Free** (verified email) - can buy Pro.
- **Pro** - unlocks Invoicing UI and ephemeral server bridges (PDF render, ISDOC extract, e-faktura, BTCPay checkout, bulk PDF import).

Local-first data stays in the browser; PRO unlocks the module and bridges.

### 3. Relay and backup

- **Dual-relay (recommended):** primary `wss://relay.satflux.io` (self-hosted on the standalone stack) + secondary `wss://free.evoluhq.com`.
- Sync uses WebSocket from the **browser** directly to relay hosts (not through Laravel).
- Relay stores **E2EE encrypted blobs** only; satflux PHP never sees plaintext invoicing data.
- Backup the Docker volume `evolu_relay_data` via `./backup.sh` (`BACKUP_EVOLU_RELAY=true` by default).
- Users must keep the **same 24-word recovery phrase** on every device.
- See [BUSINESS_INVOICING.md - Multi-device sync](BUSINESS_INVOICING.md#multi-device-sync-runbook).

### 4. Still server-side (ephemeral bridge)

These need PRO and hit Laravel briefly; data is not stored server-side long-term:

- PDF generation and e-mail send for invoices
- ISDOC field extraction from expense PDFs
- Bulk PDF expense import
- Excel export of expense list and attachment ZIP
- SK e-faktura inbound (when `EFAKTURA_ENABLED=true`)
- BTCPay checkout for invoice payment links

## Production checklist

### A. Edit environment (on the server)

In `.env.standalone` (or your deploy `ENV_FILE`):

```env
VITE_INVOICING_LOCAL_FIRST=true
INVOICING_LOCAL_FIRST=true

# Dual-relay (recommended production)
VITE_EVOLU_RELAY_URL=wss://relay.satflux.io
VITE_EVOLU_RELAY_URL_SECONDARY=wss://free.evoluhq.com
RELAY_SITE_ADDRESS=relay.satflux.io

# Recommended with local-first onboarding
SEED_FIRST_REGISTRATION=true
```

DNS: add an **A** record `relay.satflux.io` → same VPS IP as the main site. `deploy.sh` generates the Caddy TLS block from `RELAY_SITE_ADDRESS`.

Do **not** commit production `.env` files. Back up the file before editing.

### B. Deploy and rebuild frontend

`VITE_*` variables are compiled into JS at **`npm run build`** time. Changing them requires a rebuild, not only `php artisan optimize:clear`.

```bash
./deploy.sh
```

`deploy.sh` passes the deploy env file into the build step so `VITE_INVOICING_LOCAL_FIRST` from `.env.standalone` is baked into the bundle even if a stale `.env` exists on disk.

After any `.env` change (including `INVOICING_LOCAL_FIRST`):

```bash
docker compose -f docker-compose.standalone.yml --env-file .env.standalone exec php php artisan optimize:clear
```

### C. Verify after deploy

1. **Build flag** - in browser devtools, open a built JS chunk or check Invoicing index for the local-first notice banner (`invoicing.local_first_notice`).
2. **Guest** - sign in as guest: Invoicing nav item should be absent or gated (no Pro).
3. **Pro user** - open Invoicing: companies load from Evolu; create company works after relay sync settles.
4. **Recovery phrase** - sign out, restore with phrase on a second browser profile: companies appear after sync (wait up to ~45 s).
5. **WooCommerce** (if used) - create document with `INVOICING_LOCAL_FIRST=true` routes to inbox integration, not legacy `BusinessDocument` rows.
6. **Dual relay** - DevTools → Network → WS: **two** WebSocket connections when Invoicing opens (`wss://relay.satflux.io` and `wss://free.evoluhq.com`, or your configured URLs).
7. **Relay container** - `docker ps` shows `satflux_evolu_relay_standalone` healthy; Caddy serves TLS on `RELAY_SITE_ADDRESS`.
8. **Failover** - stop the primary relay container (`docker stop satflux_evolu_relay_standalone`); create/sync should still work via secondary after refresh (then start primary again).

### D. Reverse proxy / CSP

Browsers connect **outbound** to relay hosts. Caddy on satflux.io terminates TLS for `RELAY_SITE_ADDRESS` and reverse-proxies WebSocket to `evolu-relay:4000`.

If you add a strict `Content-Security-Policy`, allow:

```
connect-src 'self' wss://relay.satflux.io wss://free.evoluhq.com ...
```

(Replace with your relay hostname(s).)

Satflux Reverb (`wss://your-domain/...`) is separate from Evolu relay.

## Rollback

1. Set `VITE_INVOICING_LOCAL_FIRST=false` and `INVOICING_LOCAL_FIRST=false` in `.env.standalone`.
2. Run `./deploy.sh` (rebuild frontend).
3. Run `php artisan optimize:clear` in the PHP container.

Server PostgreSQL invoicing data from before rollout is **unchanged** (not deleted). Users who created data only in Evolu during local-first mode will not see it in server mode until you export/restore via recovery phrase again.

## Troubleshooting

| Symptom                              | Likely cause                            | Fix                                                                      |
| ------------------------------------ | --------------------------------------- | ------------------------------------------------------------------------ |
| Invoicing still uses server API      | Old JS bundle                           | Redeploy with `VITE_INVOICING_LOCAL_FIRST=true` and hard-refresh browser |
| Empty company list after login       | Relay sync not finished or wrong phrase | Wait on index; confirm same recovery phrase; see multi-device runbook    |
| Duplicate companies                  | Created company before sync completed   | Delete duplicate in Company settings - Danger zone                       |
| WooCommerce creates server documents | `INVOICING_LOCAL_FIRST` false on server | Set server flag + `optimize:clear`                                       |
| Flags mismatch warning during deploy | Only one of the paired flags set        | Align both flags in env file                                             |
| Relay WS fails on primary only       | DNS/TLS/Caddy for `RELAY_SITE_ADDRESS`  | Check `docker/caddy/Caddyfile.relay`, Caddy logs, A record               |
| Only one WS connection in DevTools   | Old JS bundle or missing secondary env  | Rebuild with `VITE_EVOLU_RELAY_URL_SECONDARY`; hard-refresh browser      |

## Related docs

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md) - module overview, plan access, multi-device sync
- [BUSINESS_EXPENSES.md](BUSINESS_EXPENSES.md) - expenses and attachments
- [DEPLOYMENT.md](DEPLOYMENT.md) - Docker standalone stack
