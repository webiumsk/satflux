# Boltz receiving pause

## Accepted scope

The owner approved removing unsupported wallet promotion, stopping new Aqua
descriptor/SamRock setup, and warning existing Boltz merchants in the application
and by email. Bull is retained as a receiving option only if the actual Satflux
integration works. Its restored mobile-wallet functionality does not qualify the
existing BTCPay Boltz integration. Both descriptor brands therefore share this
pause; this is not a claim that both mobile wallets are broken.

Source baseline: Satflux `4cf070923619e952069ac92ab6407c7636bcad66`.
Implementation branch: `fix/boltz-provider-pause`.

## Integration contract and acceptance

| Area | Required behavior |
| --- | --- |
| New setup | Reject new Boltz descriptors, wallet-type selection and SamRock activation before local or BTCPay mutations. Include the safe code `boltz_receiving_unavailable`. |
| Existing stores | Add `boltz_service_alert` to both local and BTCPay-backed store responses. Current Boltz stores see a persistent warning and a link to change their connection. |
| Readiness | A known suspended provider cannot report ready from an older cached response. Configuration and operational availability remain distinct. |
| User interface | Remove unsupported receiving endorsements from landing pages and new setup. Keep supported alternatives and all five locales. |
| Operational email | Preview counts by default; explicit sending; verified non-guest owners only; current affected stores only; durable campaign deduplication; no personal information in CLI output. |
| Recovery | Preserve wallet records, original invoices, settlement evidence, pending-payment safety, reconciliation and recovery. Never switch merchants automatically or mark unknown settlement paid. |
| Authorization | Preserve Sanctum, CSRF, store ownership and audited support/admin access; wallet replacement confirmation and Cashu guards remain mandatory. |

The pause does not rewrite payment settings in an external BTCPay deployment.
Existing invoices, direct BTCPay clients and WooCommerce still need operator
attention to their configured receiving path. Application warnings must not imply
that historical payments were cancelled or that on-chain payments were disabled.

## Ownership and validation plan

| Task | Owner | Writable scope | Evidence |
| --- | --- | --- | --- |
| Backend availability and guards | Backend specialist | Services, controllers, requests, Boltz config and relevant PHP tests | Denied setup has no writes/HTTP; authorization and supported-wallet regressions |
| Landing, setup and store alerts | Frontend specialist | Vue/TypeScript, five JSON locales and frontend tests | Unsupported inputs absent, alert CTA, locale parity, lint/type/build |
| Merchant email | Notification specialist | New campaign command/model/migration/notification, five PHP translation files and campaign tests; stale Blink email recommendation | Dry run, deduplication, current-recipient checks, transport failure retention |
| Integration and documentation | Main agent | Runbook, user documentation, changelog, isolated tooling and shared state | Reproducible checks and independent QA/security review |

Specialists do not publish Git changes or deploy. No production database, wallet,
merchant credentials or actual email recipient is used for verification.

## Vendor evidence

- [Boltz suspension notice](https://bol.tz/) and
  [pinned suspension implementation](https://github.com/BoltzExchange/boltz-web-app/commit/9cc7b2ab368148f9a74f4a90db97bdd426c39751).
- [Aqua support, October 9](https://jan3.zendesk.com/hc/en-us/articles/21952862266779-How-does-Lightning-work-in-AQUA)
  and [October 12](https://jan3.zendesk.com/hc/en-us/articles/34940028331163-What-are-the-fees-on-AQUA):
  sending through Indra; receiving unavailable.
- [Bull restoration, October 20](https://www.bullbitcoin.com/blog/lightning-and-liquid-are-back-on-bull-wallet):
  mobile Lightning send/receive restored. Its replacement order API has not been
  qualified against our BTCPay daemon or recovery contract.

## Rollout and recovery

Merge, deployment and production campaign execution remain separate operator
steps. The application change must be reviewed and tested before those steps.
Do not uninstall/reset Boltz, delete swap records, recreate wallets, or change
provider URLs as a shortcut. Preserve matching BTCPay and Boltz data/recovery
material before an approved receiving-provider change.

New SamRock OTP creation, QR display and completion are blocked. Status lookup
and cancellation remain available. A QR already copied outside Satflux cannot be
revoked by hiding the interface; check any outstanding pairing with the BTCPay
operator. This limitation does not change payment-refund recovery.

`services.boltz.receiving_enabled` is an internal compatibility switch, defaulting
to false. It has no environment or public control. Healthy legacy-contract tests
enable it explicitly; production restoration needs a reviewed provider/daemon
contract and matching frontend changes, not a toggle based on public fee data.

## Operational email campaign

Apply the normal reviewed deployment and its migration before running the command.
The new table stores only campaign delivery claims; migrations and scheduled tasks
do not send this email. First preview without recipient names or email addresses:

```sh
php artisan wallet:notify-boltz-unavailable --dry-run --locale=sk
```

After the operator has reviewed the counts and approved campaign execution:

```sh
php artisan wallet:notify-boltz-unavailable --send --locale=sk
```

Supported message languages are en, sk, es, de and cs; the default is en. Existing
accounts do not persist a preferred email language, so the explicit command locale
applies to the campaign. Changing the language does not create another campaign.

Only verified non-guest owners with a currently affected store are eligible.
One email groups that owner's current stores, linking with local UUIDs. Eligibility,
ownership and affected stores are checked again before transport. Guests and
unverified users retain the in-application warning without receiving this email.

The unique owner/campaign claim is written before transport to prevent concurrent
or repeated command invocations from resending the notice. A successful transport
result means an accepted delivery attempt, not proof that an inbox received mail.
An interruption after claiming or an uncertain SMTP result is held for operator
reconciliation, with no automatic resend. There is no exactly-once SMTP guarantee.
Do not delete claims or use a migration rollback to force another delivery. Preserve
this ledger during an application rollback and reconcile uncertain outcomes with
the mail provider before any separately reviewed retry.

## Validation

Use disposable fixtures with synthetic credentials, in-memory SQLite and array
mail. `BoltzReceivingPauseTest` covers blocked setup, unchanged records, cached
readiness, authorization, retries and retained SamRock status/cancellation.
`BoltzServiceNotificationTest` exercises preview, duplicate claims, changed
eligibility, real notification cloning, cancelled channels and uncertain delivery.
The frontend banner and setup tests cover warnings, supported alternatives and
disabled descriptor/SamRock entry points. Existing affected wallet and settlement
suites remain part of regression validation.

Run the existing PHP tests, Pint, PHPStan, API export checks, frontend tests,
lint, typecheck baseline, locale parity and production build. Preserve existing
ratchets and require independent QA/security review of the final source snapshot.
Record exact receipts with the implementation handoff; package availability or
mocked provider responses do not establish restored real-world Boltz receiving.
No production campaign was executed during development.
