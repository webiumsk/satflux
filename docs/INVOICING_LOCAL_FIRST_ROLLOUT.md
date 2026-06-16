# Local-first invoicing - production rollout

Enable Evolu-based invoicing (companies, contacts, documents, expenses in the browser) on satflux.io production. Pair this guide with [DEPLOYMENT.md](DEPLOYMENT.md) and [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md).

## What changes

| Layer | Flag | Effect |
| ----- | ---- | ------ |
| Frontend (build-time) | `VITE_INVOICING_LOCAL_FIRST=true` | SPA reads/writes invoicing via Evolu SQLite + E2EE relay; server invoicing CRUD APIs are bypassed for core data |
| Backend (runtime) | `INVOICING_LOCAL_FIRST=true` | `Company::usesServerInvoicing()` returns false globally; WooCommerce inbox routing uses local-first paths |

Both flags should be **on together** in production. Mismatch causes confusing behaviour (e.g. WooCommerce hooks still writing server rows while the UI shows Evolu).

Optional:

- `VITE_EVOLU_RELAY_URL` - default `wss://free.evoluhq.com` (see `resources/js/evolu/config.ts`)
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

| Endpoint | Purpose |
| -------- | ------- |
| `GET /api/invoicing/migration/status` | Counts of server-side companies/contacts/documents/expenses |
| `GET /api/invoicing/migration/export` | Full `InvoicingDataSnapshot` JSON for browser `restoreInvoicingSnapshot` (throttled 5/hour) |

CLI for support: `php artisan invoicing:export-for-evolu {email}` writes JSON to `storage/app/private/`.

### 2. Plan and onboarding model

- **Guest** - no Business Invoicing (BTCPay demo only).
- **Free** (verified email) - can buy Pro.
- **Pro** - unlocks Invoicing UI and ephemeral server bridges (PDF render, ISDOC extract, e-faktura, BTCPay checkout, bulk PDF import).

Local-first data stays in the browser; Pro unlocks the module and bridges.

### 3. Relay and backup

- Sync uses WebSocket to the Evolu relay (default `wss://free.evoluhq.com`).
- Users must keep the **same 24-word recovery phrase** on every device.
- See [BUSINESS_INVOICING.md - Multi-device sync](BUSINESS_INVOICING.md#multi-device-sync-runbook).

### 4. Still server-side (ephemeral bridge)

These need Pro and hit Laravel briefly; data is not stored server-side long-term:

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

# Recommended with local-first onboarding
SEED_FIRST_REGISTRATION=true

# Optional: self-hosted relay instead of free.evoluhq.com
# VITE_EVOLU_RELAY_URL=wss://relay.yourdomain.example
```

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
6. **Relay** - browser Network tab: WebSocket to `wss://free.evoluhq.com` (or your `VITE_EVOLU_RELAY_URL`) connects without errors.

### D. Reverse proxy / CSP

No extra nginx rules are required for the Evolu relay (browser connects outbound to `wss://free.evoluhq.com`). If you add a strict Content-Security-Policy, allow `connect-src` to your relay host.

Satflux Reverb (`wss://your-domain/...`) is separate from Evolu relay.

## Rollback

1. Set `VITE_INVOICING_LOCAL_FIRST=false` and `INVOICING_LOCAL_FIRST=false` in `.env.standalone`.
2. Run `./deploy.sh` (rebuild frontend).
3. Run `php artisan optimize:clear` in the PHP container.

Server PostgreSQL invoicing data from before rollout is **unchanged** (not deleted). Users who created data only in Evolu during local-first mode will not see it in server mode until you export/restore via recovery phrase again.

## Troubleshooting

| Symptom | Likely cause | Fix |
| ------- | ------------ | --- |
| Invoicing still uses server API | Old JS bundle | Redeploy with `VITE_INVOICING_LOCAL_FIRST=true` and hard-refresh browser |
| Empty company list after login | Relay sync not finished or wrong phrase | Wait on index; confirm same recovery phrase; see multi-device runbook |
| Duplicate companies | Created company before sync completed | Delete duplicate in Company settings - Danger zone |
| WooCommerce creates server documents | `INVOICING_LOCAL_FIRST` false on server | Set server flag + `optimize:clear` |
| Flags mismatch warning during deploy | Only one of the paired flags set | Align both flags in env file |

## Related docs

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md) - module overview, plan access, multi-device sync
- [BUSINESS_EXPENSES.md](BUSINESS_EXPENSES.md) - expenses and attachments
- [DEPLOYMENT.md](DEPLOYMENT.md) - Docker standalone stack
