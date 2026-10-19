# Satflux security and reliability diagnostics (2026-10-18/19)

This runbook is for an operator with authorized, read-only access. It does not
require API keys, wallet material, customer data, or changing production state.
Run commands from the production application host or an approved read-only
operations workstation. Do not paste raw logs, full URLs, email addresses, IPs,
store IDs, invoice IDs, webhook secrets, request bodies, wallet configuration,
or Cashu data into a ticket. Share only aggregates and the redacted fields named
below. Use the production timezone and record whether timestamps are UTC.

## Application and database

### Confirm application build and time window

```bash
docker compose exec -T php php artisan about --only=environment,laravel,php
date -u '+%Y-%m-%dT%H:%M:%SZ'
```

Purpose: identify the deployed application/runtime and align the log window.
Where: application host, in the deployment directory.
Expected: environment and framework/runtime versions, current UTC time.
Interpretation: compare the deployed revision to the candidate under review;
this does not prove which code handled an earlier event unless deployment history
is also checked.
Redact: hostnames, local paths, environment values, and any output that includes
configuration or credentials. Share only version numbers and UTC offset.

### Guest-account creation totals (no account identifiers)

```bash
docker compose exec -T php php artisan tinker --execute='$guests = DB::table("users")->where("is_guest", true); $hourly = (clone $guests)->whereBetween("created_at", ["2026-10-18 00:00:00", "2026-10-20 00:00:00"])->selectRaw("DATE_TRUNC('"'"'hour'"'"', created_at) AS hour, COUNT(*) AS count")->groupByRaw("DATE_TRUNC('"'"'hour'"'"', created_at)")->orderBy("hour")->get()->toArray(); echo json_encode(["total_guest_accounts" => $guests->count(), "guest_created_by_hour" => $hourly], JSON_PRETTY_PRINT);'
```

Purpose: compare aggregate guest account creation volume to the reported accounts
68-76 without exposing user IDs, synthetic email addresses, or personal data.
Where: application container; `tinker` is used only for read-only SELECT/counts.
Expected: a total and per-hour counts.
Interpretation: a burst alone does not prove automation. Correlate hourly counts
with the route-category proxy command below and available rate-limit metrics.
Redact: do not add/select emails, IDs, IPs, recovery keys, or session data.

### Store and terminal count aggregates for guest-owned accounts

```bash
docker compose exec -T php php artisan tinker --execute='$perGuest = DB::table("users")->join("stores", "stores.user_id", "=", "users.id")->leftJoin("pos_terminals", "pos_terminals.store_id", "=", "stores.id")->leftJoin("apps", function ($join) { $join->on("apps.store_id", "=", "stores.id")->where("apps.app_type", "=", "PointOfSale"); })->where("users.is_guest", true)->groupBy("users.id")->selectRaw("users.id, COUNT(DISTINCT stores.id) AS store_count, COUNT(DISTINCT pos_terminals.id) AS terminal_count, COUNT(DISTINCT apps.id) AS cached_pos_app_count")->get(); echo json_encode(["guest_accounts_with_local_stores" => $perGuest->count(), "accounts_with_multiple_local_stores" => $perGuest->where("store_count", ">", 1)->count(), "accounts_with_multiple_local_terminals" => $perGuest->where("terminal_count", ">", 1)->count(), "max_local_terminals_per_guest" => $perGuest->max("terminal_count") ?? 0, "accounts_with_multiple_cached_pos_apps_including_archived" => $perGuest->where("cached_pos_app_count", ">", 1)->count(), "max_cached_pos_apps_per_guest_including_archived" => $perGuest->max("cached_pos_app_count") ?? 0], JSON_PRETTY_PRINT);'
```

Purpose: detect aggregate resource use among guest accounts that currently have
at least one local store. The PoS-app count uses Satflux's cached local app rows,
includes archived rows, and may lag BTCPay; treat it as an upper bound rather
than an exact count of active apps.
This intentionally does not list accounts or stores.
Where: application container.
Expected: aggregate counts only.
Interpretation: local counts above one item per guest indicate a policy mismatch;
they do not include guests with no local store, prove BTCPay's current state, or
identify who created records or whether a request was malicious.
Redact: share aggregates only; never append identifier columns.

### Confirm the cache backend used by guest provisioning locks

```bash
docker compose exec -T php php artisan tinker --execute='$store = config("cache.default"); echo json_encode(["cache_store" => $store, "cache_driver" => config("cache.stores.".$store.".driver")], JSON_PRETTY_PRINT);'
```

Purpose: confirm which cache backend implements the guest store and PoS setup
locks.
Where: application container.
Expected: cache store name and driver only.
Interpretation: every application worker handling these routes must share an
atomic lock-capable backend. The guest store lock has a 30-minute lease; verify
the configured BTCPay provisioning timeouts remain well below that bound. An in-process or per-container cache does not
serialize concurrent provisioning across workers. Confirm topology and actual
deployment configuration with the operator; this command does not inspect secret
cache credentials.
Redact: share only the store and driver names; never print cache config values,
connection strings, or passwords.

## Laravel log aggregates

### Error categories and webhook outcomes

```bash
docker compose exec -T php sh -lc 'for f in storage/logs/laravel-2026-10-18.log storage/logs/laravel-2026-10-19.log; do [ ! -r "$f" ] || sed -nE "s/^\\[([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}).*(BTCPay webhook received without signature header|BTCPay webhook signature verification failed|BTCPay webhook rejected: no webhook secret configured|BTCPay webhook rejected: store identity is not configured|Wallet config verification failed|BTCPAY_WEBHOOK_BASE_URL must explicitly identify the reachable Satflux callback service\\.|System health transition \\(no SYSTEM_ALERT_EMAIL configured\\)).*$/\\1 \\2/p" "$f"; done | sort | uniq -c'
```

Purpose: report message frequencies by minute without dumping full log records.
Where: application container. Adjust filenames to the retained rotation names.
Expected: counts grouped by minute and redacted message category.
Interpretation: failed signature checks are rejected by application code before
an event is recorded or job dispatched; correlate timestamps with BTCPay delivery
status and proxy access status. A missing webhook URL is configuration failure,
not an invalid remote signature. Counts do not prove source attribution.
Redact: review the output before sharing; remove IPs, store identifiers, email,
payload fragments, complete exception traces, and any URL query string.

### Wallet verification status without configuration values

```bash
docker compose exec -T php php artisan tinker --execute='echo json_encode(DB::table("wallet_connections")->selectRaw("status, COUNT(*) AS count, SUM(CASE WHEN drift_detected_at IS NOT NULL THEN 1 ELSE 0 END) AS drift_count, SUM(CASE WHEN config_verified_at IS NULL THEN 1 ELSE 0 END) AS never_verified_count")->groupBy("status")->get()->toArray(), JSON_PRETTY_PRINT);'
```

Purpose: capture current aggregate wallet state, not payment configuration.
Where: application container.
Expected: status and counts only.
Interpretation: compare before/after snapshots around a transient BTCPay outage.
The current source catches a failed read before changing fingerprint, timestamp,
or drift fields; production build/runtime behavior still needs operator evidence.
Redact: never select `config_snapshot`, fingerprints, store IDs, or wallet data.

## BTCPay and reverse proxy

### BTCPay health and recent application errors

```bash
docker compose ps --status running
docker compose logs --timestamps --since '2026-10-18T20:45:00Z' --until '2026-10-18T21:30:00Z' --no-color btcpayserver 2>&1 | sed -nE 's/^.*(2026-10-18T(20:[0-5][0-9]|21:[0-2][0-9])).*(HTTP 400|HTTP 502|HTTP 302|gRPC|Boltz|CashuMelt|quote poll|exception|error).*$/\1 \3/Ip' | tr '[:upper:]' '[:lower:]' | sort | uniq -c
```

Purpose: correlate the wallet-check 502 window and separate Boltz/CashuMelt
failures from registration activity.
Where: production host; replace the service name with the actual compose service.
Expected: process status and minute-bucketed diagnostic categories only.
Interpretation: a running container does not establish API or plugin health.
Look for upstream timeout/DNS/gRPC evidence and quote polling lifecycle errors.
Do not attribute either integration to guest registration without matching
request or trace evidence.
Redact: the command emits minute/category counts only; do not substitute raw log output or
share invoice/quote/store identifiers, addresses, credentials, preimages, proofs,
or personal data.

### Proxy and BTCPay request status aggregates

```bash
sudo awk '{split(substr($4,2), t, ":"); if (t[1]=="18/Oct/2026" && ((t[2]=="20" && t[3]>="45") || (t[2]=="21" && t[3]<="30")) && $9 ~ /^[0-9][0-9][0-9]$/) print t[1] ":" t[2] ":" t[3],$9}' /var/log/nginx/access.log | sort | uniq -c
```

Purpose: aggregate proxy response codes for the incident window without printing
request paths, query strings, client addresses, or headers.
Where: reverse-proxy host; adjust the file path to the configured conventional
nginx combined access log. This is a log-file query, not a journald query.
Expected: minute-bucketed counts by HTTP status.
Interpretation: timestamps use the timezone offset in nginx access logs; convert
to UTC before correlating with application/Docker logs. Compare 502 counts per
minute with BTCPay and Docker service logs. If access logs use a custom format,
have the operator adapt field positions locally and retain aggregate-only output.
For 302 responses, an operator should inspect the `Location` target locally and
share only scheme, hostname, and path template after confirming it is the
expected BTCPay origin. Never replay the request or follow the redirect with an
Authorization header.
Redact: do not share raw access lines, cookies, forwarded headers, query strings,
client IPs, or complete `Location` values containing identifiers.

To correlate guest account creation and Lightning Address submissions, use the
standard nginx combined access log and emit only minute, route category, method,
and status. Adjust the file path to the configured log file; do not print raw
access lines.

```bash
sudo awk '$7 ~ /^\/api\/auth\/guest([?]|$)/ {kind="guest-create"} $7 ~ /^\/api\/stores\/[^/]+\/lightning-addresses\/[^/]+([?]|$)/ {kind="lightning-address-submit"} kind != "" {split(substr($4,2), t, ":"); minute=t[1] "/" t[2] ":" t[3]; method=$6; gsub(/"/,"",method); print minute,kind,method,$9; kind=""}' /var/log/nginx/access.log | sort | uniq -c
```

Purpose: compare minute-bucketed route traffic and status outcomes with guest
account creation times, without printing client IPs, account IDs, addresses, or
paths with store/username values.
Where: reverse-proxy host; only for conventional nginx combined access logs.
Expected: counts by date/minute, route category, method, and response status.
Interpretation: request counts can show correlation in time, but do not prove
that a given request created a specific guest or that the caller was automation.
Rate-limit outcomes need existing proxy/application metrics; standard access
logs alone may not distinguish middleware rejection.
Redact: share only the grouped output. Never share raw access lines, client IPs,
forwarded headers, full paths, query strings, or user-supplied usernames.

### Safely reproduce the legacy apps-list redirect without following it

The supplied historical BTCPay log does not record the `Location` header, so its
redirect destination is not known from that evidence. This read-only request
targets only the configured BTCPay origin and explicitly disables redirects. Run
from the application container with an authorized local Store UUID; the merchant
API key stays in process memory and is never printed.

```bash
docker compose exec -T php php artisan tinker --execute='$store = App\Models\Store::where("id", "LOCAL_STORE_UUID")->firstOrFail(); $base = rtrim(config("services.btcpay.base_url"), "/"); $path = "/stores/".$store->btcpay_store_id."/apps"; $response = Illuminate\Support\Facades\Http::withToken($store->user->getBtcPayApiKeyOrFail())->withOptions(["allow_redirects" => false])->timeout(15)->get($base.$path); $location = $response->header("Location"); $parts = $location ? parse_url($location) : []; $origin = static function (array $url): ?string { if (!isset($url["scheme"], $url["host"])) return null; $scheme = strtolower($url["scheme"]); $port = $url["port"] ?? ($scheme === "https" ? 443 : ($scheme === "http" ? 80 : null)); return $scheme."://".strtolower($url["host"]).":".(string)$port; }; $relativeSameOrigin = $location && !isset($parts["host"]) && str_starts_with($location, "/"); echo json_encode(["status" => $response->status(), "location_origin_matches_configured_origin" => $relativeSameOrigin || ($origin($parts) !== null && $origin($parts) === $origin(parse_url($base))), "location_path_template" => isset($parts["path"]) ? App\Services\BtcPay\BtcPayClient::redactEndpointForLog($parts["path"]) : null, "location_has_query" => isset($parts["query"])], JSON_PRETTY_PRINT);'
```

Purpose: inspect the status and a sanitized redirect destination without
forwarding the authorization header to the destination.
Where: application container; replace `LOCAL_STORE_UUID` locally with the
authorized local UUID only.
Expected: status, whether the redirect scheme/host/effective port match the
configured BTCPay origin (or the redirect is a relative path), and a sanitized
path template.
Interpretation: a different host must be treated as untrusted; do not follow it.
This request may create an access-log entry at BTCPay but does not mutate state.
Redact: never share the local UUID, store ID, API key, raw `Location`, query,
response body, or full URL. Share only the fields printed by the command.

### Container DNS resolution (read-only)

```bash
docker compose exec -T php getent hosts btcpayserver
BOLTZ_HOST='replace-with-the-known-non-secret-Boltz-DNS-name'
docker compose exec -T btcpayserver getent hosts "$BOLTZ_HOST"
```

Purpose: check service-name resolution from each relevant container.
Where: production Docker host. Replace the example value in the local shell only
with the configured non-secret hostname; do not print `.env` or share shell history.
Expected: one or more current IPs for a resolvable name; no output/nonzero status
indicates resolution failure.
Interpretation: DNS success does not prove gRPC/TLS/daemon health; follow with
the existing documented health endpoint or service-level status command.
Redact: internal IPs and hostnames before sharing. Never include environment
values, credentials, wallet data, or swap secrets.

## Boltz and CashuMelt safe status

### Boltz swap-state counts

The audited Satflux and CashuMelt repositories do not contain the Boltz swap
database schema or a Boltz-specific status endpoint. No safe active-swap query
can therefore be prescribed from the available source. First identify the exact
BTCPay plugin/daemon and version, then use only its documented read-only health
and aggregate swap-status interface. Do not improvise a database query or invoke
retry/recovery operations. If the plugin exposes no read-only aggregate, active
swap risk remains unknown pending an operator/vendor-supported diagnostic.

Purpose: identify whether any active swaps are pending or at risk.
Where: exact plugin's documented operations interface after its identity and
version are established.
Expected: count and age by state only, if supported.
Interpretation: active state is not proof of loss; match it against the plugin's
documented recovery state machine and backups before any action.
Redact: all swap IDs, secrets, preimages, refund material, addresses, invoices,
and merchant identity. Do not return individual rows.

### CashuMelt polling aggregates

If the operator has an approved PostgreSQL read-only connection configured as
the `cashumelt-readonly` service, run this query. The service file must omit a
password and use a protected `.pgpass` or `PGPASSFILE`; this command opens a
read-only transaction and groups only settlement metadata. It does not select
quote, store, invoice, mint, proof, or wallet identifiers.

```bash
PGSERVICE=cashumelt-readonly psql -X -v ON_ERROR_STOP=1 -c 'BEGIN TRANSACTION READ ONLY; SELECT "SettlementState", "FailureReasonCode", "NeedsManualReview", COUNT(*) AS row_count, MAX("RetryCount") AS max_retry_count, MIN("LastCheckedAt") AS oldest_last_check, MAX("LastCheckedAt") AS newest_last_check FROM "BTCPayServer.Plugins.CashuMelt"."CashuMeltPaymentRequests" GROUP BY "SettlementState", "FailureReasonCode", "NeedsManualReview" ORDER BY "SettlementState", "FailureReasonCode", "NeedsManualReview"; ROLLBACK;'
```

Purpose: count old and current failed quote-poll rows and determine whether
last-check timestamps keep advancing.
Where: approved operations workstation with a read-only PostgreSQL role,
`~/.pg_service.conf` entry and `~/.pgpass` or `PGPASSFILE` with mode 0600.
Never put a password in a DSN, command line, shell history, or ticket.
Expected: rows grouped by state/reason/manual-review flag, counts, retry maximum,
and time bounds. A missing column/table indicates version/schema mismatch and
must be resolved by inspecting plugin version and schema metadata, not by altering
production.
Interpretation: repeated HTTP 400 alone is not a settlement result. `FAILED` /
`mint_poll_error` rows with advancing check times may indicate an older retry
loop; compare with deployed plugin version and logs. Preserve all recovery
material and use only the plugin's documented safe recovery workflow.
Redact: share only the grouped fields and UTC time bounds; never share the
service file, password file, DSN,
quote/store/invoice IDs, mint URL, tokens, proofs, blinded data, or merchant
details.

## Required evidence to return

Return (1) deployed Satflux revision and CashuMelt/BTCPay versions, (2) UTC
timestamp bounds, (3) aggregate guest/store/terminal counts, (4) grouped webhook
failure reasons and BTCPay delivery outcome counts, (5) proxy/BTCPay 502 and 302
status aggregates plus only the verified redirect origin/path template, (6)
wallet status counts before and after the failure window, (7) Boltz daemon/DNS
health and active/pending swap counts/oldest age, and (8) CashuMelt state/retry
count/last-poll aggregates. Do not return raw records, secrets, or identifiers.
