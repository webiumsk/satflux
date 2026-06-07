# OFAC / sanctions compliance - implementation plan

Legal basis: Terms of Service section **5a** (sanctions, export controls, prohibited jurisdictions). Contractual language alone is insufficient; OFAC enforcement in crypto expects **documented screening** at onboarding and on material account changes.

This document plans backend implementation for satflux.io. It does not replace advice from qualified sanctions counsel.

## Goals

1. **Block** sign-ups from comprehensively sanctioned jurisdictions (geo signal).
2. **Screen** registration identifiers (email, optional name) against SDN / EU Consolidated / equivalent lists.
3. **Retain audit evidence** that screening ran (result, timestamp, provider reference).
4. **Enforce** consistently across all registration paths (email/password, LNURL-auth, Nostr).
5. **Support manual review** for ambiguous hits without exposing list details to end users.

## Non-goals (phase 1)

- Continuous re-screening of all active users (phase 2).
- Wallet address / on-chain screening (merchant responsibility; optional phase 3).
- Billing-address screening beyond subscription checkout metadata.

## Registration touchpoints

All paths must call the same screening service before `User` creation or before first verified login:

| Path | Controller / service |
|------|----------------------|
| Email + password | `RegisterController` -> `RegistrationService` |
| LNURL-auth (new user) | `LnurlAuthController` |
| Nostr (new user) | `NostrAuthController` |
| Guest upgrade to full account | `AccountController` (if applicable) |

## Architecture

```
Request (IP + email [+ name])
  -> GeoJurisdictionGuard (MaxMind or Cloudflare CF-IPCountry)
  -> SanctionsScreeningService (provider API)
  -> compliance_screenings row (immutable log)
  -> allow | block | pending_review
```

### 1. Configuration (`config/compliance.php`)

```php
'enabled' => env('COMPLIANCE_SCREENING_ENABLED', false),
'geo_block_enabled' => env('COMPLIANCE_GEO_BLOCK_ENABLED', true),
'screening_provider' => env('COMPLIANCE_SCREENING_PROVIDER', 'null'), // complyadvantage | opensanctions | custom
'blocked_country_codes' => ['CU', 'IR', 'KP', 'SY', /* region codes via geo rules */],
'blocked_region_codes' => ['UA-43', 'UA-14', 'UA-09'], // Crimea, Donetsk, Luhansk - if provider supports
'fail_closed' => env('COMPLIANCE_FAIL_CLOSED', true), // block if provider unavailable in prod
'retention_years' => 7,
```

Environment secrets: `COMPLYADVANTAGE_API_KEY`, `MAXMIND_LICENSE_KEY`, etc.

### 2. Database

**Migration: `compliance_screenings`**

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | |
| `user_id` | uuid nullable | set after user created, or null if blocked pre-create |
| `subject_type` | string | `registration`, `account_update`, `rescreen` |
| `subject_email` | string | normalized email |
| `subject_name` | string nullable | |
| `ip_address` | string nullable | |
| `country_code` | char(2) nullable | from geo lookup |
| `geo_blocked` | boolean | |
| `screening_provider` | string | |
| `screening_status` | enum | `clear`, `hit`, `error`, `skipped` |
| `screening_reference` | string nullable | provider case/search id |
| `screening_payload_hash` | string nullable | SHA-256 of redacted response for integrity |
| `decision` | enum | `allowed`, `blocked`, `pending_review` |
| `decision_reason` | string nullable | internal code, not user-facing list names |
| `created_at` | timestamp | |

**Migration: `users` additions (optional phase 1b)**

- `compliance_status` enum: `active`, `blocked`, `pending_review`
- `last_screened_at` timestamp

Indexes: `subject_email`, `user_id`, `created_at`, `decision`.

### 3. Services

**`App\Services\Compliance\GeoJurisdictionGuard`**

- Resolve country from `Request::ip()` using MaxMind GeoIP2 or Cloudflare `CF-IPCountry` header (prefer header when behind CF).
- Return `allowed` | `blocked` with ISO country code.
- Log to `compliance_screenings` even when blocked before API call.

**`App\Services\Compliance\SanctionsScreeningService`**

Interface `SanctionsScreeningProvider`:

```php
interface SanctionsScreeningProvider {
    public function screen(ScreeningSubject $subject): ScreeningResult;
}
```

Implementations:

- **`NullSanctionsScreeningProvider`** - dev/test; always `clear`.
- **`ComplyAdvantageProvider`** (recommended starter) - REST search, SDN + EU lists, audit trail id.
- **`OpenSanctionsProvider`** - lower cost / self-hosted option; evaluate latency and SLA.

`ScreeningSubject`: email, optional display name, country code from geo step.

`ScreeningResult`: `clear` | `potential_match` | `error`, provider reference, redacted raw payload for hash storage.

**`App\Services\Compliance\ComplianceGate`**

Orchestrator used by `RegistrationService`:

```php
public function assertRegistrationAllowed(Request $request, string $email, ?string $name = null): void
```

Throws `ComplianceBlockedException` -> HTTP 403 with generic i18n message (no "you are on SDN list").

### 4. User-facing behavior

- **Geo block**: "Registration is not available from your location."
- **List hit / pending**: same generic message; internal alert to `compliance@satflux.io` or admin queue.
- **Provider error** (prod, `fail_closed=true`): block registration; log `screening_status=error`.
- Do not leak whether block is geo vs sanctions.

Add locale keys: `messages.compliance_registration_unavailable` (en/sk/es).

### 5. Admin / operations

- **Artisan**: `compliance:rescreen {email}` for manual re-check.
- **Admin UI** (phase 2): list `pending_review`, approve/deny with audit log.
- **Alerting**: Slack/email on `hit` or `error` rate spike.
- **Retention**: keep `compliance_screenings` minimum 7 years (align with `docs/DATA_RETENTION.md`).

### 6. Testing

| Test | Assert |
|------|--------|
| `ComplianceGateTest` | blocked country -> 403, screening row `geo_blocked=true` |
| `ComplianceGateTest` | provider hit -> 403, `decision=blocked` |
| `ComplianceGateTest` | clear -> registration proceeds, row `decision=allowed` |
| `AuthTest` | all three auth paths invoke gate when enabled |
| Http::fake() | provider timeout + `fail_closed` -> 403 |

Use `COMPLIANCE_SCREENING_ENABLED=false` in CI by default; dedicated test enables with fake provider.

### 7. Rollout phases

**Phase 0 - Legal (done)**

- [x] ToS section 5a (EN/SK/ES)
- [ ] Replace GDPR Art. 27 placeholders in `operator.ts` with appointed representative details

**Phase 1 - Geo block only (implemented on `feature/ofac-compliance`)**

- [x] `config/compliance.php` + env flags
- [x] `GeoJurisdictionGuard` (config override, CF-IPCountry, MaxMind GeoLite2) + `compliance_screenings` migration
- [x] `ComplianceGate` hooked into `RegisterController` (email/password path)
- [x] `compliance:update-geoip` + monthly schedule
- [x] `tests/Feature/ComplianceGateTest.php`
- [ ] Enable in prod: `COMPLIANCE_SCREENING_ENABLED=true` after MaxMind DB download
- [ ] Extend gate to LNURL-auth, Nostr, guest upgrade (Phase 1b)

**Phase 2 - Local list screening (implemented on `feature/ofac-compliance`)**

- [x] `sanctions_entries` table + daily `compliance:sync-sanctions-lists` (OFAC SDN XML + EU via OpenSanctions CSV; direct EU XML returns 403 from many hosts)
- [x] `LocalSanctionsScreeningProvider` (exact match on normalized name / email local-part)
- [x] `COMPLIANCE_LIST_SCREENING_ENABLED` flag
- [x] Gate extended to LNURL-auth, Nostr, guest upgrade
- [ ] Paid API provider (ComplyAdvantage) when budget allows

**Phase 3 - Operations (2-3 days)**

- Admin notifications for hits
- `compliance:rescreen` command
- Runbook section in this doc / `DEPLOYMENT.md`

**Phase 4 - Hardening (optional)**

- Periodic re-screen cron (e.g. quarterly active users)
- VPN / Tor heuristics (do not over-block; counsel review)
- Export screening evidence for audit requests

## Blocked jurisdictions reference

Align with ToS 5a.2(a). ISO 3166-1 alpha-2 country blocks (phase 1):

- `CU` Cuba
- `IR` Iran
- `KP` North Korea
- `SY` Syria

Sub-national (phase 1b, provider-dependent):

- Crimea, Donetsk, Luhansk (UA regions) - use MaxMind Insights or provider geo rules

Russia / Belarus: **not** comprehensive OFAC country blocks as of plan date; do not geo-block without counsel update (sectoral sanctions differ).

## Provider selection notes

| Provider | Pros | Cons |
|----------|------|------|
| ComplyAdvantage | API-first, audit ids, crypto clients | Paid |
| Refinitiv World-Check | Enterprise standard | Expensive, sales cycle |
| OpenSanctions | Open data, cheap | Self-operated, SLA on you |
| OFAC SDN API (Treasury) | Authoritative US list | US only; no EU; build matching yourself |

Recommendation: start **ComplyAdvantage** or similar mid-tier API for MVP; store `screening_reference` for every check.

## Evidence checklist (audit defense)

For each registration attempt, retain:

1. Timestamp (UTC)
2. Email screened (normalized)
3. IP + derived country code
4. Provider name + reference id
5. Decision (`allowed` / `blocked` / `pending_review`)
6. Hash of provider response (not full PII in logs)

## Open decisions

1. **GDPR Art. 27 representatives** - supply real name/address/email for `LEGAL_GDPR_REPRESENTATIVES` in `operator.ts`.
2. **Screening provider** - budget and preferred vendor.
3. **Fail-open vs fail-closed** in staging (prod should be fail-closed).
4. **Pending review SLA** - who approves false positives (founder vs external compliance).

## Related files

- Legal copy: `resources/js/content/legal/documents.ts`, `imprintDpa.ts`, `operator.ts`
- Registration: `app/Services/Auth/RegistrationService.php`, `app/Http/Controllers/Auth/*`
- Consent timestamps: `app/Support/Legal/LegalConsent.php`
