# Tatra B-mail automatic-confirmation candidate

Consumer base: cd951b3b7e27a2f30278c0d414d603f483b83f62. Owner priority: Tatra banka
through existing Mailgun intake. This implements a positive automatic path; the
previous manual-only source state did not complete that product requirement.
No current original bank message is available for live-format qualification.

## Implemented result

A signed Mailgun original-MIME delivery enters the dedicated .mime endpoint. Raw
MIME remains byte-exact and encrypted in queued/failed payloads. The existing
Node runtime verifies the actual aligned bank signature over the complete message,
signed recipient and MIME controls, then extracts the one supported plain-text
part. PHP binds its digest to those exact bytes and sends only verified content
to the production strict Tatra parser. The supported bank prefix provides actual
receiving account, positive EUR amount, balances and the ordered reference before
free text. Generic company importing and FIO/NOP are unchanged.

The credit fingerprint excludes invoice reference, note and rendering; it is a
conservative inferred identity, not a bank-issued unique ledger identifier.
Duplicate identity cannot prove a second invoice was paid. QR case is preserved
for case-sensitive provider/NOP matching. Unknown provider outcomes remain review
only. Unsupported formats or missing/invalid signatures cannot confirm payments;
temporary DNS/process/report failures retain queue retries.

The reference being chosen by a payer is normal. Prior selection probes did not
prove theft or underpayment merely from that fact. Provenance, unambiguous
transaction interpretation and once-only allocation are the required boundaries.

## Coordinator checks and attribution

Final focused PHP: **158 passed / 687 assertions**, including the actual
PHP-to-production-Node-CLI crypto path, real parser and provider-report contract.
Synthetic keys are generated in memory and only public test DNS records are used.
The suite covers original-byte preservation/ciphertext storage, tampering,
recipient retargeting, private downstream exceptions, provider capability,
unknown/mixed outcomes, retry identity, uppercase QR, strict-format attacks and
unchanged company importing. No injected trusted-row positive establishes the
Mailgun end-to-end gate. Earlier DTO service tests remain clearly hypothetical.

Author Node suite: **26 passed, zero failed/skipped**. Independent security also
executes all 26 on Node22.18.0. Locked runtime pins are mailauth8.0.4 and
mailparser3.14.1 with scoped joi18.3.1 override; nested audit reports zero
vulnerabilities. Root dependency pins are unchanged.

PHPStan full project and final touched-service checks pass. Affected-file Pint,
API export/check, OpenAPI validation, locale parity and all five user guides pass.
Deployment/changelog guards pass 53 cases after a normal subprocess-capable rerun.
Lint retains 48 existing warnings under the unchanged52 ceiling; typecheck retains
120 existing baseline errors and adds none. The PHP chain is qualified locally
with existing Node24.22.1; CI installs the declared Node22 runtime. Earlier old
fixture Node20 runs are diagnostic and not the supported-runtime qualification.

Initial author/test harness failures included a signing-fixture API mismatch,
sandbox EPERM for subprocesses, and a test-only DNS environment variable not
inherited by Symfony Process. They were corrected or rerun under normal tool
approval before success; no signature requirement or assertion was weakened.
Initial PHPStan findings were fixed in types without raising a baseline.

## Independent review

[Security report](bmail-security-source-review.md) accepts its exact
[24-file source snapshot](bmail-security-source-manifest.json), SHA-256
`c1c800547d800f0380d9e41aec5cb4decd825075b4d45dc1e4e254d13ccab399`.
It found and verified repairs for decrypted-content exception persistence and QR
case compatibility. The [design review](bmail-security-design.md) records the
historical-template trust argument and correction to the earlier impact claim.
The coordinator [41-file manifest](reviewed-source.json) additionally binds CI/deployment installation,
generated contracts and five-locale documentation; shared report prose is excluded.

[Independent QA](qa.md) accepts the [41-file snapshot](qa-source-manifest.json).
It executes 108 PHP tests / 579 assertions on the final source, 26 Node crypto/CLI
cases and one extra private output-cap fixture / two assertions. All pass without
skips. The one-line nested deployment installer is separately inspected and its
shell syntax passes; no deployment is executed.
No installed host or final plugin archive is qualified by these core tests.

## Operational limits and next steps

The [operator runbook](../../SEPA_CONFIRMATION_ISOLATION_RUNBOOK.md) describes locked
runtime installation and Mailgun .mime destination configuration. Those external
settings and deployment remain separate owner gates. A current private original
message is required to establish actual bank signing, charset, To/header and
layout compatibility. Historical integrator examples plus synthetic QR adaptation
qualify this specific parser profile, not every current Tatra notification.
SLSP, HTML/multipart, unsigned and unrecognized layouts remain review-only.

No real bank transfer, wallet, production database, merchant message, global tool
installation, new network service, merge or deployment occurred. Existing provider
contract-v2 account binding and non-atomic recording recovery still apply. Keep
app queue-encryption keys and provider database/Data Protection backups with the
relevant recovery data. No bank payload or signing key is committed here.
