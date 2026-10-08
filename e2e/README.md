# Browser tests

The Playwright setup project signs into `E2eTestSeeder`'s shared account once.
Most specs reuse its storage state. Authentication specs start with empty
cookies. Run only against a development/test database and the BTCPay stub.

## Recovery enrollment across tabs

`recoveryEnrollment.spec.ts` uses two pages in one browser context to share
real session cookies. Named steps cover stale guest signup, stale Profile enrollment,
CSRF rejection, deliberate enrollment, blocked password login, signed recovery
on a fresh device, session persistence on reload, and store creation after
restore. The phrase is read through the real clipboard button, without parsing
CSS layout or numbering. Laravel's authentication limiter stays enabled; the
scenario observes remaining capacity and waits only when needed. A login that
receives 429 can be resubmitted after `Retry-After` because the limiter rejected
it before authentication. Other writes are not retried.

Set `E2E_AUTH_RECOVERY=1` when running `E2eTestSeeder` to create dedicated accounts
from `e2e/fixtures/recovery-accounts.json`. Both the seeder and spec use these
synthetic credentials, independently of `E2E_USER_PASSWORD`. The original account
stays password-enabled; each repeat/retry gets a separate enrolling account.
The fixture pool supports the CI's one run plus two retries, or three local
repeats without retries. The spec checks that the pool covers the configured
execution count; extend the JSON pool for larger repeat/retry combinations.
Re-seed before a new run to reset enrolled keys. The seeder refuses production.

Store cleanup runs in `finally` and clears leftovers before creation, scoped to
the dedicated enrolling account. Playwright traces include manually created
contexts; a fresh-device failure also attaches a screenshot of that page.
Artifacts can contain the synthetic recovery phrase and session cookies. Never
run this scenario against real merchant accounts or real BTCPay credentials.

With the test app configured for the Greenfield stub, run:

```sh
E2E_AUTH_RECOVERY=1 E2E_BTCPAY=1 php artisan db:seed --class=E2eTestSeeder
E2E_AUTH_RECOVERY=1 E2E_BTCPAY=1 E2E_SEEDED_USER=1 npx playwright test e2e/recoveryEnrollment.spec.ts
```

To check repeatability, seed once and run three executions without setup logins:

```sh
E2E_AUTH_RECOVERY=1 E2E_BTCPAY=1 php artisan db:seed --class=E2eTestSeeder
E2E_AUTH_RECOVERY=1 E2E_BTCPAY=1 E2E_SEEDED_USER=1 npx playwright test e2e/recoveryEnrollment.spec.ts --repeat-each=3 --retries=0 --no-deps
```

The CI E2E job enables these flags and runs the scenario alongside the existing
suite. The scenario can take a few minutes due to the real 5/min/IP limiter.
