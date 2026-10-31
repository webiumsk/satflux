# PR412 outside-diff bank-email corrections

Base consumer: `4b607bb20b6c29c36ae5d113ca743490c0699044`. Review:
[5719928957](https://github.com/webiumsk/satflux/pull/412#pullrequestreview-5719928957).
The three findings were outside the incremental diff and did not appear as new
inline threads. Successful checks did not establish that the findings were absent.

## Final behavior

Current Tatra/SLSP parsers retain generic company-import metadata but provide no
trusted E2E or explicit-variable-symbol evidence to automatic SEPA matching.
Their text formats do not establish bank ownership of reference-like labels in
payer remittance. A genuine credit header and verified sender cannot close that
boundary. These emails require manual bank verification. FIO/NOP and provider
c1912bf, SEPA 0.8.2.0 and delivered archives remain unchanged.

An eligible hypothetical structured row returning provider `unknown` requires
manual review and does not increase reported counts, including mixed batches.
Transport exceptions retain queue retry and stable dedup identity. The optional
native origin-attestation API object documents three required members. Runtime
still accepts incomplete legacy notifications without granting verified origin.
Five-locale setup hints and user articles describe the current capability.

This evidence supersedes classic-Tatra automatic-support statements and changed
consumer source hashes in the initial integration qualification. Earlier complete
PHP/frontend/provider/archive results remain historical for their reviewed bytes.
The reporting service's positive tests use explicitly hypothetical structured
bank evidence; they do not claim a currently supported automatic email format.

## Coordinator validation

- Focused financial suite: 103 tests / 276 assertions.
- API documentation: 11 tests / 230 assertions; generated schema and source hashes current.
- PHPStan: no errors. Pint: all five changed PHP files pass.
- Locale parity: pass, four existing informational SK extras.
- ESLint: zero errors, 48 existing warnings within 52 ceiling.
- Typecheck: no new errors, 120 unchanged baseline errors.
- Diff whitespace: pass.

The first API-suite attempt overlapped parser edits and detected stale
route-coverage source hashes (27 pass, one fail including bank-origin units).
Regeneration after source freeze and the full API-suite rerun passed. An author
run first exposed a removed still-needed test import; it was restored before the
103-test pass. No failed attempt is represented as a pass or assertion weakened.

## Independent security review

[Security](security.md) accepts the frozen 18-file scope, including the integration
record, with [source hashes](reviewed-source.json). Manifest SHA-256:
`0693db66ed0f3f4c5cd572f65ea36633b9eb3d7f1ab60f5cbc9595f835c21a90`.
The reviewer executed 30 baseline/current assertions across five synthetic parser
cases and independently checked the optional-parent/required-child schema.
Generic import rows remain equal; no current email reference reaches reporting.

## Independent QA

[QA](qa.md) accepts all 17 functional/API/test/locale/user-guide file hashes in
[its manifest](qa-reviewed-source.json), SHA-256
`33f6dfdaefe23b14b2dde8f23fdd98741b294e49234f58365b03545c24cc4304`.
Independent execution passes the 103-test focused suite / 276 assertions plus
five disposable adversarial tests / 36 assertions (108 / 312 total). The extra
fixture checks five baseline/current generic-row comparisons and real-webhook
acceptance of empty/partial origin attestations with no verified-domain marker.
No broad or installed-host qualification is inferred from these focused results.

## Boundaries

Only synthetic offline SQLite/array fixtures and mocked HTTP were used. No bank
traffic, real funds, live credentials, production database, package rebuild,
merge or deployment. Exact-head remote CI remains a separate publication gate.
Primary Graphify query returned unrelated heuristics; the target has no graph,
so targeted reads were used without installing or rebuilding tools. Null or
unrecognized provider responses remain separate off-contract accounting hardening;
current parsers cannot reach reporting, and this service does not mark host
invoices paid. Existing external-recording atomicity/recovery limits remain.
