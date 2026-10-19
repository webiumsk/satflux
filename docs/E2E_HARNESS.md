# E2E harness (Playwright) - P2 fáza 3

## Čo je pokryté

| Spec | Scenáre | Podmienky |
|---|---|---|
| `e2e/auth.spec.ts` | login stránka sa načíta; email formulár dostupný cez `?tab=email` v oboch build módoch; nepriihlásený redirect z `/stores`; zlé prihlasovacie údaje ukážu chybu | žiadne |
| `e2e/emailLogin.spec.ts` | prihlásenie seedovaného používateľa → dashboard; Sanctum session prežije reload; prihlásený je presmerovaný preč z `/login` | `E2E_SEEDED_USER=1` + seeder |
| `e2e/invoicingGuard.spec.ts` | hard-gate: `/invoicing` bez recovery frázy na zariadení presmeruje na `/account` restore flow | + `E2E_INVOICING_LOCAL_FIRST=1` a build s `VITE_INVOICING_LOCAL_FIRST=true` |
| `e2e/btcpay.spec.ts` | životný cyklus obchodu + faktúry cez Greenfield stub (create, invoice, settle+webhook, expire, delete) | `E2E_BTCPAY=1` + bežiaci stub + seeder s `E2E_BTCPAY=1`; viď [BTCPAY_E2E_SCENARIOS.md](BTCPAY_E2E_SCENARIOS.md) |
| `e2e/localFirstCheckout.spec.ts` | local-first fráza bind → firma so store → issued faktúra → ephemeral BTCPay checkout → settle webhook → paid v UI | ako btcpay.spec + `E2E_INVOICING_LOCAL_FIRST=1` (local-first build) |

CI nemá reálny BTCPay Server; BTCPay scenáre bežia proti in-memory Greenfield
stubu (`e2e/btcpay-stub/server.mjs`, zapojený cez `BTCPAY_BASE_URL`) -
[BTCPAY_E2E_SCENARIOS.md](BTCPAY_E2E_SCENARIOS.md).

## Authenticated fixtures and real rate limits

`/api/auth/*` retains its 5/min/IP limit. `e2e/auth.setup.ts` saves the shared
account's state in gitignored `e2e/.auth/user.json`. When the stub/local-first
scenario is enabled, it also signs in the dedicated checkout account and saves
`e2e/.auth/checkout.json`. Local-first checkout uses that state explicitly; earlier
store/invoice scenarios cannot exhaust its separate 120/min/user API budget.
`E2eTestSeeder` creates both ordinary PRO fixtures only under `E2E_BTCPAY=1` and
still refuses production. No production limiter is disabled or increased.

Authentication specs (`auth.spec`, `emailLogin.spec`) start with empty state.
The initial login budget is two setup logins + two email-login tests + one invalid
login = five requests. Recovery tests observe actual rate-limit capacity and wait
when necessary. Serial retries reuse stored authentication instead of signing in
again. Run the complete suite to verify accumulated request budgets.

API helpers negotiate JSON and check successful responses before parsing or
continuing cleanup. Checkout assertions check the actual POST status before waiting
for its link. Failure output reports HTTP status without exposing document snapshots,
session state or response bodies. Reports/traces remain private synthetic evidence.

## Lokálne spustenie

Proti Docker stacku (`http://localhost:8080`):

```bash
# jednorazovo: fixture používateľ (e2e@satflux.test / E2e-password-123)
docker compose exec php php artisan db:seed --class=E2eTestSeeder

npx playwright install chromium          # jednorazovo
E2E_SEEDED_USER=1 E2E_INVOICING_LOCAL_FIRST=1 npm run test:e2e
```

- `APP_URL` prepíše baseURL (default `http://localhost:8080`).
- Bez `E2E_SEEDED_USER=1` sa seedované scenáre čisto skipnú - beží len
  neautentifikovaný smoke.
- `E2E_INVOICING_LOCAL_FIRST=1` nastavte len ak build mal
  `VITE_INVOICING_LOCAL_FIRST=true` (inak guard scenár právom zlyhá).
- Report: `npx playwright show-report` (pri lokálnom zlyhaní sa otvorí sám).

## CI job

`.github/workflows/ci.yml` → job **E2E (Playwright)**, paralelný s backend a
frontend jobmi:

1. PHP 8.3 + Node 22, sqlite DB, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`
   (bez redis/BTCPay), `APP_URL=http://localhost:8000`.
2. Build frontendu s `VITE_INVOICING_LOCAL_FIRST=true` a prázdnou
   `VITE_EVOLU_RELAY_URL` (local-only sync - žiadny mŕtvy default relay).
3. `php artisan migrate` + `db:seed --class=E2eTestSeeder` (seeder sa v
   produkcii odmietne spustiť).
4. `php artisan serve` na pozadí + čakanie na `/login`.
5. `npx playwright test` (chromium, 2 retry, trace on-first-retry);
   HTML report sa pri zlyhaní uploadne ako artifact (7 dní).

## Konvencie pre nové scenáre

- Zdieľané helpery a env gaty: `e2e/support.ts` (loginWithEmail,
  seededUser, hasSeededUser, hasLocalFirstBuild).
- Scenáre závislé na dátach gate-ujte cez `test.skip(!hasSeededUser, ...)` -
  neseedovaný lokálny beh má skipovať, nie zlyhať.
- Selektory: preferujte stabilné atribúty (typ inputu, role) pred CSS
  triedami; `?tab=email` na odhalenie email formulára.
- Playwright config: `workers: 1` (Sanctum session state), retries len v CI.
