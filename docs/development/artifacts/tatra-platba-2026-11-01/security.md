# Independent security review: exact Tatra Platba description

Reviewer: `/root/bank_mime_diagnostic_security` (independent of implementation).
Date: 2026-11-01. Repository: Satflux, scoped `tatra-ascii-wire-profile` worktree.
Reviewed base: `a29b727aae0751dfe554a4cb86da3738f7c5d375`, plus the four frozen files below.
Disposition: ACCEPTED within the bounded Satflux parser/security scope. No verified new vulnerability or unresolved source blocker was found. This is not publication, deployment, provider implementation or payment-replay authorization. Independent QA owns provider replay/concurrency/recovery and broad bank regression acceptance.

## Scope and exact bytes

| File | SHA-256 |
| --- | --- |
| app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php | 1e24a3eaf5ef207bed4c0159515cab7a3350256bd120092d5f383bbc1da66991 |
| tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php | 9bb63933532427590f2ec05a64b6f612f37b8062cd57ad7dae0829306e387edf |
| tests/Feature/SepaBmailTest.php | cb99e3fb74ba24b043280bdaf17d28b20148a6ce492140bf29184cc839a45575 |
| tests/Feature/BankMimeConfirmationTest.php | 0227f583448b82271981e1a2ebbfe58ee7733a220183df6471dfe1d3e8f78a55 |
| docs/development/TATRA_PLATBA_PROFILE.md | f42829119939e291425c6da53914f84c960c0dffad5cf9c6ec01ad05ee713e31 |

Hashes were verified before execution and after execution. A byte comparison proves that replacing the single `(CCINT|CSO)` occurrence with `(CCINT|CSO|Platba)` in the baseline parser produces the exact frozen production file. All other parser bytes are identical. The production diff across `app` and `runtime` is that single changed line.

Targeted baseline comparison also finds no modifications to bank ingress, MIME verification/runtime, the Tatra general parser, SEPA confirmation service, provider client or parsed-transaction DTO. There is no alias list, provider change, database migration, identity rewrite, new retention, log payload or endpoint in this scope. Graphify was unavailable in the scoped worktree; the main-agent graph query was stale and lacked these parser edges. No graph rebuild or installation occurred.

## Findings and security reasoning

No blocking finding.

1. Exact grammar remains bounded: `TatraBankSepaEvidenceParser.php:90` accepts only the additional case-sensitive literal, retaining the fully anchored four-digit bank, one-to-six-digit prefix and one-to-ten-digit account form. Content, greeting, header/date/receiving-account, three balances, trusted-reference and complete-tail validation remain unchanged (`:45–108`). Arbitrary descriptions, near-match labels, encoded structural injection and unexpected tail fields remain rejected.
2. Identity schema and legacy values remain unchanged (`TatraBankSepaEvidenceParser.php:115–119`). Platba retains its own literal in the v1 tuple. No assertion that Platba is equivalent to CCINT or CSO is made. Within each type, permitted rendering, reference and free-tail changes do not alter the event identity.
3. Trusted reference provenance is preserved. `TatraBankEmailParser.php:32–35` evaluates the strict original body only for the expected sender profile; `:117–119` copies only strict evidence into trusted fields. `SepaEmailConfirmationService.php:52–56,145–161` still requires its existing eligibility gates and only those trusted reference candidates. The existing contract-version check (`:73–76`) is unchanged. Actual body tampering and recipient retargeting are rejected by synthetic cryptographic integration tests.

### Informational residual: no cross-label equivalence claim

Provider evidence was read from the qualified read-only provider worktree at `e8d7fbcdfd2bda91b5a3a8145a708dcb9dd7201e`. `SepaPaymentMethodHandler.cs:123–136` binds a generated reference to its original invoice and Store; the reference is the request primary key. `SepaMatchingService.cs:112–124` locks the matching reference/Store and returns Duplicate for a confirmed request before considering the event fingerprint. `SepaPaymentRecorder.cs:94–124` uses the reference as the stable payment ID and recognizes the same recorded ID during recovery.

A changed label alone, with the same authenticated reference, therefore does not provide a second-invoice settlement path. Such a path would additionally require two genuinely bank-authenticated renditions of one economic credit containing different valid invoice references and labels, or unauthorized reference reassignment. No such capability was established. Payer-controlled note injection does not satisfy the strict ordered reference field and tail checks. This is an informational residual bank-reference-stability assumption, not a verified new vulnerability caused by the literal extension. Existing CCINT/CSO identities already distinguish labels.

The discarded contract-v3/alias-table proposal is not required by the demonstrated incident and is not implemented. This review does not assert universal duplicate suppression across bank corrections that change both label and reference. If such a bank capability is evidenced later, review that concrete path independently. No severity is assigned to an unverified exploit.

## Independently executed verification

All execution used the existing PHP image (PHP 8.3.31/PHPUnit 11.5.50), existing locked vendor/Node dependencies, no package installation, Docker `--network none --pull=never`, a non-root user, read-only app/vendor/runtime mounts and a masked `.env`. Configuration cache was absent. The runner explicitly selected testing, SQLite in memory, array cache/session/mail, synchronous queue, null logging and synthetic provider credentials. No Redis, production service, real mail, merchant credential or funds were accessed. Only private `/tmp` probe/report files were written.

- Private `tatra-platba-security-probe.php`: PASS **839 checks**, including **672 exact baseline output/stage comparisons across 336 legacy inputs**, 12 positive synthetic Platba frames, label/numeric boundary rejection, content/header/account/tail rejection, duplicated/embedded/encoded reference injection, stable rendering and trusted-row provenance. Foreign senders do not acquire strict reference or identity evidence.
- Bounded PHPUnit filter: PASS **63 tests / 294 assertions**, no skipped or failed tests. Includes 53 strict parser cases and ten native/MIME/reference-binding cases. Actual synthetic signed-MIME and authenticated ASCII-wire fixtures execute through the normal parser/report boundary. Provider responses in consumer tests are stubs; those tests do not prove provider settlement idempotency.
- Additional bounded negative filter: PASS **18 tests / 52 assertions**, no skipped or failed tests. Covers missing/unverified native bank origin, incomplete/ambiguous evidence, old plugin contract, tampered signed body and recipient retargeting.
- Combined distinct selected PHPUnit coverage: **81 tests / 346 assertions**. The full bank suite was deliberately left to QA.
- Warning investigation: initial whole-suite discovery emitted one existing PHPUnit doc-comment metadata deprecation for `RaffleApiTest::test_guest_cannot_access_raffle_routes`; that file has no baseline diff. A discovery-only command identified it without executing unrelated tests. Explicit parser-file rerun passed 53/159 without warnings; this rerun is not added to the combined count.
- Final structural comparison: PASS, exact one-literal production delta; all four frozen hashes unchanged.

Private runner: `/tmp/tatra-platba-security-run-php.sh`; private baseline: `/tmp/tatra-platba-security-baseline.php`. Outputs contain only fixed test summaries, not bank-message content.

## Dependencies and limits

Frozen-source acceptance does not establish actual production deployment or settlement. The owner supplied sanitized field structure; agents did not receive the original private bank message. Existing cryptographic proof, trusted reference, account/amount/currency and provider contract checks remain required. QA independently owns same-reference opaque-hash replay/concurrency/recovery against the qualified provider, and main owns full static/API/CI checks and publication gates. No changes to auth/CSRF, tenant isolation, credentials/encryption, billing/entitlements, browser privacy or external integrations are introduced by the reviewed one-literal delta.
