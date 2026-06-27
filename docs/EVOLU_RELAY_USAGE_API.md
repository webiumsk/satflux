# Evolu relay - usage HTTP API (relay agent task)

satflux Profile shows **relay backup size** when this endpoint exists on the self-hosted relay (e.g. `evolu.satflux.io`). The relay stores **E2EE ciphertext only** - this API returns **bytes and timestamps**, not invoice counts.

## Endpoint

```
GET /usage/{ownerId}
```

Example:

```bash
curl -sS "https://evolu.satflux.io/usage/f60eLX4bok7g"
```

### Response `200 application/json`

```json
{
  "ownerId": "f60eLX4bok7g",
  "storedBytes": 3460300,
  "quotaBytes": 52428800,
  "firstActivityAt": "2026-06-25T18:25:12.000Z",
  "lastActivityAt": "2026-06-25T20:39:00.000Z"
}
```

| Field | Source |
|-------|--------|
| `ownerId` | URL path (validated OwnerId) |
| `storedBytes` | Evolu relay storage logical bytes for this owner |
| `quotaBytes` | `QUOTA_PER_OWNER_MB * 1024 * 1024` from relay env |
| `firstActivityAt` | earliest stored op timestamp (ISO 8601) or `null` |
| `lastActivityAt` | latest stored op timestamp (ISO 8601) or `null` |

### Errors

| Status | When |
|--------|------|
| `400` | missing/invalid `ownerId` in path |
| `404` | owner has no data yet on relay |
| `405` | non-GET |

## CORS (required for satflux.io Profile)

Browser fetches from `https://satflux.io`. Traefik or the usage handler must return:

```
Access-Control-Allow-Origin: https://satflux.io
Access-Control-Allow-Methods: GET, OPTIONS
Access-Control-Allow-Headers: Accept
```

Also allow `https://www.satflux.io` if you use it. For local dev: `http://localhost:8080`, `http://localhost:8000`.

Handle `OPTIONS` preflight on `/usage/*`.

## Implementation sketch (`/opt/evolu`)

Add a small HTTP server **alongside** the WebSocket relay (same container or Traefik route):

1. Read owner usage from relay SQLite (same DB as `@evolu/nodejs` relay - owner usage table via storage API or direct SQL on `satflux-evolu-relay.db`).
2. Map `ownerId` string from URL to stored bytes + timestamps.
3. Bind usage HTTP on `127.0.0.1:4001` and route Traefik:

```yaml
# Traefik labels (example)
traefik.http.routers.evolu-usage.rule: Host(`evolu.satflux.io`) && PathPrefix(`/usage`)
traefik.http.routers.evolu-usage.entrypoints: websecure
traefik.http.routers.evolu-usage.tls: "true"
traefik.http.services.evolu-usage.loadbalancer.server.port: "4001"
```

WebSocket relay stays on port `4000` with rule `Host(`evolu.satflux.io`) && !PathPrefix(`/usage`)` or higher priority for `/usage`.

Reference: [getbased-relay](https://github.com/elkimek/getbased-relay) admin/metrics pattern; Evolu `OwnerUsage` type in `@evolu/common` (`storedBytes`, `firstTimestamp`, `lastTimestamp`).

## Security

- **No secrets** in response - only aggregate size per `ownerId`.
- `ownerId` is pseudonymous; knowing it does not decrypt data.
- Optional rate limit per IP on `/usage/*`.
- Do **not** expose full DB download via this route.

## Smoke test

```bash
# After browser push from satflux Profile
curl -sS "https://evolu.satflux.io/usage/YOUR_OWNER_ID" | jq .
# storedBytes should be > 0, lastActivityAt recent
```

## satflux client

`resources/js/services/evoluRelayUsageApi.ts` calls:

`GET https://{relay-host}/usage/{ownerId}`

If the endpoint is missing (404/timeout), Profile shows only **local** stats.
