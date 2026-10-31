# SEPA confirmation isolation on BTCPay 2.4.7

## Current bank-email capability

PR412 review 5719928957 identified a body-controlled reference boundary missed
by the initial qualification below. The consumer now leaves all current
Tatra/SLSP email references untrusted for SEPA, requires manual verification and
excludes provider `unknown` outcomes from reported counts. Five-locale guidance
and the optional origin-attestation schema match this behavior. The
[follow-up evidence](artifacts/sepa-247-email-review/README.md) supersedes earlier
classic-Tatra automatic-support claims and the changed consumer source hashes.
Provider c1912bf and its delivered archives are unchanged.

## Source and delivery scope

The owner requested integration of the prepared SEPA isolation repair with the merged 2.4.7 compatibility work and publication of plugin source. Consumer branch `fix/sepa-confirmation-isolation-247` starts at merged Satflux `4ff5c32a` and applies prepared `885168f2`. Companion provider branch `fix/btcpay-247-compatibility` combines `ed250c2` and `8afed1a`, pins exact host `2868deedd9d911b715fec7306ea9cada36d86bff`, and identifies the repaired plugin as 0.8.2.0 with host minimum 2.4.7. Compatibility-only 0.8.1.1 remains a different archived candidate.

No merge, GitHub Release, bank operation, production migration, installation or deployment follows from source/package preparation. Companion publication and tests have separate repository histories. Existing unrelated primary-checkout work is preserved.

## Contracts and independent ownership

| Area | Owner | Acceptance |
| --- | --- | --- |
| Provider source and regression tests | Plugin specialist | Original-store and receiving-account binding, contract v2, recognized verified bank origin, positive credit, correct amount/currency/reference, safe dedup/retry and unchanged explicit manual path |
| Consumer source, API and five-locale docs | Backend specialist | Refuse old capability, authenticate native bank attestation independently of Mailgun delivery, complete observed credit evidence, stable retry identity and existing tenant/scoped-key guards |
| Security source review | Independent security specialist | Verify the actual integrated trust boundaries; preserve known explicit manual opt-in and non-atomic external-recording recovery limitations |
| Runtime and artifact review | Independent QA specialist | Synthetic SQLite/PostgreSQL only, negative/cross-version/retry evidence, exact source hashes, final archive identity checked separately |
| Host restoration, integration, shared state and Git delivery | Coordinator | Exact source guard, durable dependency metadata, separate branches/PRs, reviewed package and fresh exact-head CI |

New consumer with old plugin fails closed on missing contract v2; old reports to the new provider cannot bypass origin/account evidence. New/new wire fields agree on the observed receiving account and credit details. The merchant's original invoice account remains authoritative after settings changes. No silent merchant-data correction, broad privilege, live funds or commercial bank integration is introduced.

## Security correction found during integration

The independent reviewer found an inherited medium issue: an integrity-consistent NOP zero/negative amount could pass a large configured tolerance. Automatic evidence now rejects nonpositive amounts before dedup reservation and recording. PostgreSQL regressions exercise actual NOP parsing, no reservation/recording on rejection, a corrected positive retry with the same event identity and unchanged explicit manual zero-due behavior. Configured tolerance for positive credits remains unchanged.

## Initial integration validation (before PR412 follow-up)

The initial isolated consumer run passed 2,253 PHP tests / 10,706 assertions. All 1,094 frontend tests pass with two workers. PHPStan, affected-file Pint, current generated API contracts, five-locale parity, full lint (48 existing warnings), typecheck (120 unchanged baseline errors) and production build pass. Existing build/deprecation notices are retained.

Independent QA executes 62 consumer tests / 149 assertions and 165 provider tests with zero failures/skips. Exact-host guard and guard regression pass. Provider host plus all eight maintained plugins and eight test projects build with SDK 10.0.403 / runtime 10.0.14; the complete provider suite also passes 907 tests across eight projects with zero failures/skips. Final shipped archive identity and fresh remote CI remain separate evidence gates.

Initial full PHP attempts failed at the test runner's default 128 MB child-process limit. Setting only the parent CLI limit did not affect Artisan's subprocess; the successful run uses a fixture-only 1 GB ini mounted into the disposable PHP container. An initial unconstrained frontend run had 11 timeouts under concurrent compilation; the unchanged source passes all tests with two workers. Native Node 22 failed before Tickets cases started; the CI-matching existing Node 24 container passes all 13 client tests. None of these harness failures was relabelled a pass or resolved by changing product validation, tests, dependencies or warning baselines.

[Independent security](artifacts/sepa-247-integration/security.md), [source/runtime QA](artifacts/sepa-247-integration/qa-source-and-runtime.md) and the [reviewed source manifest](artifacts/sepa-247-integration/reviewed-source.json) distinguish actual runtime evidence from source review and prospective archive/CI gates. The security manifest contains 22 provider and 26 consumer files and has SHA-256 `9d227c2ad3a9ed433f60e344d61f6131b13954fc2dabb6855ebc2d9d095f6942`.

## Operator sequence and limitations

Install the independently qualified repaired provider first, verify actual settings `confirmationContractVersion: 2`, then deploy the reviewed consumer. Keep plugin database and Data Protection keys together and reconcile pending invoices using their original intent. The plugin reservation transaction is not atomic with host payment persistence; preserve stable-reference retry/reconciliation. Do not downgrade to unbound automatic confirmation to work around incomplete evidence.

The existing anonymous checkout-manual endpoint remains a merchant opt-in default-off feature; it is not verified bank proof. Native receiving-MTA DKIM/DMARC attestation must be configured and verified by the operator before automatic bank-mail behavior is claimed. All currently recognized Tatra/SLSP email formats require manual review: no proven bank-owned reference boundary separates reference labels from payer remittance. Receiving-MTA attestation alone cannot resolve that boundary. Generic company bank imports and automatic FIO/NOP confirmation remain unchanged. Production installation, real bank latency/coverage and a successful deployed revision are not established by these fixtures.

Primary Graphify was queried before exploration; target worktree graphs are absent and no graph was rebuilt or installed. The exact host dependency's lost temporary Git metadata was restored into durable private storage, preserving the previous checkout; tracked upstream source remains unmodified. No global credentials/trust configuration or upstream repository publication changed.
