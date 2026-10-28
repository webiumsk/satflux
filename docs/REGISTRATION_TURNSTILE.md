# Registration Turnstile

Turnstile protects fresh guest-account creation and the legacy email/password registration endpoint when that endpoint is enabled. Existing accounts, stored guest addresses, verification flags, account recovery, sign-in, upgrades and payment integrations are unchanged. Creation rate limits and proxy/firewall configuration remain separate controls.

## Runtime configuration

Create a **Managed** widget in Cloudflare Dashboard > Turnstile for the public Satflux registration hostname. Configure only hostnames that actually serve the application, such as `satflux.io`; include `www.satflux.io` only if it serves registration too. Use separate widgets for staging and production. No global Bot Fight Mode or Cloudflare DNS migration is required.

Set these in the **Satflux backend** environment, not Chorala and not public Vite variables:

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=<public-site-key>
TURNSTILE_SECRET_KEY=<private-server-secret>
TURNSTILE_HOSTNAMES=satflux.io
```

The secret must stay in the server's protected environment or secret store. Never send it in chat, commit it or include it in browser configuration. Site Key is public. `TURNSTILE_HOSTNAMES` is a comma-separated exact hostname allowlist; an empty value uses the hostname from configured `APP_URL`, never the incoming Host header. The expected widget action is `register`.

The default is disabled for a compatible rollout. When explicitly enabled, missing keys, invalid production test-key configuration or an unavailable verification service block **new account creation**. They do not silently disable protection. Cloudflare public testing credentials must not be used in production.

## Rollout

1. Complete review and CI for the backend and frontend together. Keep `TURNSTILE_ENABLED=false` until both are deployed.
2. Prepare the real Managed widget, allowed hostname and protected server secret. Use staging and synthetic fixtures for automated tests.
3. Set the runtime values and reload Laravel configuration through the normal deployment process. No Vite rebuild is needed to change the Site Key.
4. Load a fresh registration document after activation so the current CSP includes the Turnstile script/frame origin. A page opened before activation may need a full reload.
5. Confirm public `GET /api/config` reports `turnstile.enabled=true`, the public Site Key and `action=register`; it must never expose the secret. Observe aggregate verification failures without logging tokens or recovery material.

The rollout must not claim activation until the deployed revision and actual public configuration are verified. A local test or a merged PR alone is not evidence of production activation. This document provides operator steps; it does not authorize automated tests against production accounts or wallets.

## Request behavior

- Fresh `POST /api/auth/guest` and enabled legacy `POST /api/auth/register` submit `turnstile_token` when protection is enabled.
- Existing authenticated guest retries keep their prior account-resolution behavior. Disabled legacy registration still returns its existing seed-first response. Login, signed recovery, passkeys and guest-to-email upgrades are separate flows.
- The server verifies the token before account writes, email sending or BTCPay provisioning. Siteverify uses a fixed HTTPS endpoint with bounded timeouts, no redirect following and no automatic registration retries. Only the secret and opaque token are submitted; no recovery phrase, public recovery key or forwarded client-IP field is sent.
- Literal success, the `register` action and the configured hostname must match. Invalid, expired, reused or mismatched proofs return safe `422` errors. Missing configuration, malformed provider responses and transport failures return safe `503` errors. Existing CSRF, authorization and throttling remain in force.
- Public configuration reflects the enabled flag even if configuration is incomplete; the browser must not treat missing data or a failed configuration request as permission to skip verification.

The browser mounts the challenge only after the phrase-backup wizard is closed. It retains the same confirmed phrase/public key in memory across verification failures and manual retries. It never submits the phrase to Siteverify or the registration API. A new challenge is needed after token expiry or consumption. Leaving or reloading the page clears that in-memory attempt, so the written phrase backup remains essential.

## CSP and privacy

When Turnstile is enabled, CSP permits `https://challenges.cloudflare.com` for scripts and frames. It does not add JavaScript eval, inline scripts or a broad network wildcard. Only the enabled signup verification step initiates loading the widget script; sign-in and recovery do not initiate that load. Removing a widget does not unload already executed third-party code from the current document. Cloudflare receives challenge/browser information as part of its verification; recovery material is excluded from application verification requests.

## Failure and rollback

A rejected proof must not create a local user, send registration mail or provision BTCPay resources. The UI offers an accessible retry without silently generating another phrase or automatically repeating account creation. Restoring an existing account remains available if new-account verification is unavailable.

An operator can set `TURNSTILE_ENABLED=false` and reload configuration to restore the previous creation flow during an incident. This removes this abuse-protection layer, so record that operational decision and monitor creation volume. It does not change accounts or bypass existing authentication, CSRF and plan rules. No automated fallback may disable protection.

## Verification evidence

Run the dedicated Turnstile feature tests, existing guest/recovery/registration regressions, CSP tests and focused browser/component cases using synthetic accounts, fake Siteverify and fake BTCPay. Test missing/invalid/expired/duplicate tokens, wrong action/hostname, provider outage, configuration failure, production dummy keys, secret exclusion, stable phrase retry and late-callback cleanup. Independently review the final source scope before publication.

Official references:

- [Server-side token validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)
- [Widget configuration](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/)
- [Content Security Policy](https://developers.cloudflare.com/turnstile/reference/content-security-policy/)
- [Turnstile plans and standalone use](https://developers.cloudflare.com/turnstile/plans/)
