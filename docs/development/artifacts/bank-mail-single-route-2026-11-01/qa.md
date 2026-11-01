> Historical evidence for initial revision `12e00fa4`, superseded by the
> [final follow-up](../bank-mail-single-route-review-2026-11-01/README.md).
> That candidate included optional compact-date normalization, now withdrawn.
> Its manifests/commands apply only to a disposable archive of `12e00fa4`,
> not the current source. Initial CI passed, but did not dismiss the review risk.

> Archived review evidence. Private fixture/runner paths below are redacted;
> they are not committed runnable tests. Use the repository commands in README.md.

# Single-route MIME implementation: independent QA

Reviewer: `/root/wallet_bmail_qa`.
Repository: Satflux core (`webiumsk/satflux`). Target worktree `fix/bank-mail-single-route`, base `c90caa40e048cdf2b740e0195fd7198bd5209fe7`.
Scope: independent read-only application review; private synthetic fixtures/reports under `<private-bank-single-route-qa>` only. Main owns test environment, shared documentation and Git. No real bank messages, merchant data, production writes, provider/network payments, publication or deployment.

Architecture matrix is in `ARCHITECTURE_QA.md`; its prior architecture baseline is `36344e6f`, not this implementation worktree. Current main architecture is `docs/development/BANK_MAIL_SINGLE_ROUTE.md`.

## Private independent cases prepared

`SingleRouteIndependentTest.php` contains seven cases (five methods plus a two-case dataset):

1. Same original-MIME endpoint delivers unsigned HTML SLSP to Company A and raw Windows1250 multipart Tatra to Company B. Both have the same VS/amount, unrelated MIME To (BCC), and spoofed owner/mode/proof request fields. Only the envelope-target company's document becomes paid, duplicate delivery creates no second transaction, no SEPA audit/provider request occurs.
2. Unsigned Store MIME with claimed Company/proof fields cannot fall back to company import or mark its matching document paid.
3. Unknown raw-company layout must not log a private synthetic From/subject/body marker.
4. Queued raw company notification must not serialize MIME subject/body or envelope sender plaintext.
5. Real token transfer Company A to Company B between prepared output and final handling must fail before persistence. Ciphertext job remains byte-identical and sanitized error has no private marker/cause.
6. Store recipient reinterpreted as Company after a prefix configuration change must fail the same final owner check, even when the internally prepared Store payload hypothetically had a verified bank marker. This internal fake is negative ownership evidence, not bank-origin or crypto proof.
7. Main authorized a bounded explicit compact booking-date correction after a native-to-MIME duplicate regression exposed processing-clock seconds in the legacy parser. Independent case parses the same compact notification on two different processing dates/times, requires the same explicit midnight booking date without any SEPA proof, and posts duplicate MIME under both clocks expecting one transaction. Missing-date fallback, classic parsing and historical rows are outside that correction.

Security reviewer separately owns malformed decoder schema/digest/proof-marker/output/error privacy probes to avoid duplicating that independent coverage.

## Verified test environment

Main prepared `sh <private-bank-single-route-tests>/run-php.sh`: current worktree `/app`, QA fixtures readonly, existing PHP vendor and Node binary/modules readonly, network disabled, SQLite `:memory:`, array cache/mail/session, sync queue and synthetic `.env`. QA independently inspected the runner and bootstrapped the actual application before tests: `testing`, `sqlite`, `:memory:`, array cache/mail/session, sync queue, null log, `configurationIsCached=false`, base path `/app`. Independent JSON comparison of current `composer.lock` versus existing installed packages found168/168 packages and0 version mismatches. No dependencies installed.

PHP 8.3.31 / PHPUnit 11.5.50. Tests use synthetic messages, real production parser/CLI, existing test-only fake DNS for synthetic Store DKIM, and HTTP fakes/stray-request prevention. No current authentic EML exists; synthetic source/API tests cannot establish live bank interoperability.

## Frozen scope and hash evidence

Main/backend explicitly handed off 12 frozen files before execution. `sha256sum -c <private-bank-single-route-backend>/FROZEN_SHA256SUMS` passed all 12 entries immediately before and after the tests. A byte-identical review manifest is copied to `<private-bank-single-route-qa>/REVIEWED_SHA256SUMS`.

The manifest covers the controller, MIME service, job, inbound email service, transient prepared-owner DTO, separate company CLI/extractor/Node tests, Company MIME feature tests, Store MIME feature tests, and the explicitly authorized compact parser/unit-test delta. This acceptance does not extend to unrelated source or infer deployment status.

The strict Store `runtime/bank-mail-verifier/cli.mjs`, `verifier.mjs`, `package.json` and `package-lock.json` remain byte-identical to base `c90caa40`; `git diff --exit-code` passed before and after execution. The protected verifier boundary was not broadened to implement company importing.

Private final fixture SHA256: `c28c173584c358cb2a0ba45548de289e511932ec9b1aa0c4935ee6604220ac53`. Private cases are review evidence and are not additional committed repository tests.

## Exact commands and results

No incomplete implementation was executed before the explicit freeze. Source/Graphify/Git inspection and private fixture preparation preceded it.

```sh
sh <private-bank-single-route-tests>/run-php.sh php vendor/bin/phpunit --do-not-cache-result \
  <private-bank-single-route-qa>/SingleRouteIndependentTest.php \
  tests/Feature/BankCompanyMimeImportTest.php \
  tests/Feature/BankMimeConfirmationTest.php \
  tests/Feature/BankInboundEmailTest.php \
  tests/Feature/SepaBmailTest.php \
  tests/Unit/TatraBankEmailParserTest.php \
  tests/Unit/SepaBankEvidenceTest.php
```

**PASS, exit 0: 151 tests / 583 assertions, 24.353 s, 72.50 MB.** This consists of 144 repository test cases plus 7 private cases. Verified API-to-Node Company Tatra/SLSP imports/matching, legacy routes, synthetic real-DKIM Store positive and denial paths, provider/retry guards, malformed/permanent input, charset/body/size/forwarding behavior, ownership races, raw privacy and cross-clock compact-date stability.

The first combined execution returned exit 2: 151 tests / 576 assertions with one private fixture setup error because PHP mbstring in the existing image does not accept `Windows-1250` as a conversion target. Replaced only that private fixture conversion with explicit synthetic CP1250 bytes (`0x8E` for Z-caron and `0xFD` for y-acute). No application files or expectations changed. The complete clean rerun above establishes final acceptance; the first run is not reported as passing.

```sh
sh <private-bank-single-route-tests>/run-php.sh node --test \
  runtime/bank-mail-verifier/test/company-extractor.test.mjs \
  runtime/bank-mail-verifier/test/verifier.test.mjs
```

**PASS, exit 0: 42 tests / 42 passed, 0 failed, 0 skipped, 5.524 s.** Includes 16 Company extraction/CLI cases and 26 unchanged strict Store signature/CLI cases. DNS is synthetic and container networking disabled.

`git diff --check`: **PASS**. All12 frozen hashes: **PASS** after execution. Strict Node/package comparison to merged base: **PASS**.

Author separately reports scoped Pint 9 files and full unchanged-baseline PHPStan pass. QA did not rerun those static tools and does not represent them as independent executions. Full unrelated application/frontend/provider/deployment suites were not run; the change is bounded to consumer MIME transport plus explicit compact-date normalization. Main's API/export/documentation/parity checks remain separately attributed.

## Verified findings and acceptance

**No blocking QA finding in the12 frozen implementation files. Accepted for source integration at the reviewed hashes.** Independent review is separate from implementation authorship and security acceptance. No publication, merge, release, deployment or external route change is authorized/performed by this report.

Boundary evidence:

- `BankMimeVerificationService::preparePayload` selects policy from server-resolved owner; Company output is explicitly copied without proof markers. Store invalid proof cannot fall through.
- `PreparedBankMimePayload` carries a transient model identity, and `BankInboundEmailService::handle` re-resolves exact class/key before parsing or persistence. Independent real token/prefix race cases reject both cross-company and cross-policy changes.
- `ProcessBankNotificationEmail::handle` keeps prepared plaintext local and serializes only the original encrypted payload, suppressing private previous exceptions. The private no-row Company test verifies decoded marker absence from actual captured logs.
- `TatraBankEmailParser::parseCompact` resets clock precision only for an explicit date. Cross-day tests demonstrate stable midnight date and duplicate MIME persistence while preserving false/null SEPA proof fields.

## Remaining limitations and operational gates

These are documented scope limits, not inferred passes or reasons to bypass proof checks:

- Historical compact rows keep their old processing-clock timestamps. Replay after the date-only correction can require manual reconciliation; no historical rewrite/migration was made.
- Compact messages without a date keep processing-time fallback and lack a new stable bank-event identity.
- Distinct otherwise identical same-day compact transactions can conservatively collide in the existing company dedup fields. Midnight represents observed date precision, not a claimed bank-issued timestamp.
- Company legacy extraction is not a comprehensive MIME validator. Unsupported root charset/encoding is rejected, while nested MIME handling retains mailparser's permissive legacy behavior within raw/process/output limits. It never becomes Store bank proof.
- Company raw 256 KiB / decoded 65,535-byte/header/process limits can reject messages whose small parsed bodies were previously accepted from larger original messages. Supported charsets and HTML/multipart profiles are synthetic compatibility evidence, not universal bank support.
- No current original bank EML is available after the owner-reported 404 retrievals. Live bank signing/layout, EU route delivery, deployed revisions/worker state and the reported payment remain unverified here.
- Compatible consumer code and every worker must precede the single existing route's `.mime` switch. Old workers reject Company MIME, so mixed-version deployment/rollback and pending encrypted jobs require coordinated handling. Changing a route does not recover an already consumed parsed delivery or authorize marking a payment paid.

No application/repository/Git/shared-state writes were made by QA. Only this report, the private fixture and copied reviewed manifest were written under the assigned temporary directory.
