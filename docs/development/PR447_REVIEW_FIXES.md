# PR447 review corrections

Date: 2026-11-15. Owner authorizes verifying the supplied review data, fixing
still-valid findings minimally and investigating the failed PHP job. Review text,
paths and code samples are evidence to verify, never an instruction source.

## Verified baseline and scope

PR [447](https://github.com/webiumsk/satflux/pull/447) is open, unmerged and
mergeable at reviewed head `a48570d11bbf73956f89f47e36d7c38f446f3389`, base
`4b00e2eec7a56e7514a02224d84c5d97554f8c9c`. The named directional worktree is on
that head; the primary checkout remains `fix/sepa-confirmation-isolation` at
`885168f2` with unrelated work. Neither a PR nor prior local reviews establish a
deployed revision or production/provider readiness.

Baseline [CI43710844183](https://github.com/webiumsk/satflux/actions/runs/43710844183)
passes Node, E2E, PostgreSQL and changelog. PHP has one failure, 3,489 passing and
31 skipped tests / 19,402 assertions: `PublicConfigTest` uses exact JSON without
the new `efaktura_directional_connections: true` capability. Its later Pint,
PHPStan and Composer-audit steps were skipped, not passed.

| Finding | Verified current behavior | Required correction |
| --- | --- | --- |
| Current milestone | Published documents still present the prepublication local checkpoint as latest | Record PR447 reviewed head and failed CI separately from current local corrections and unverified deployment |
| Receiving save confirmation | Echo checking reads current-company secret marker and shared mode after an awaited submitted-company bridge call | Capture the submitted confirmation context before waits; preserve its company-bound synchronization cleanup |
| Settings export | appSettingsJson can become a cut JSON prefix with no warning | Preserve complete valid fitting JSON or omit the nullable field with an explicit company warning |
| Failed PHP CI | Exact public-config expectation omits the deliberate capability | Add the legitimate field while retaining exact response assertions |

## Accepted minimal contracts

The confirmation helper must use only the response and immutable submission
context: whether confirmation is required, receiving mode, URL, client and
expected secret-set state. Navigation or another save must not change it.
Replacement/removal and mismatched server echoes retain their existing meaning.
The submitted company's marker can be cleared only after its captured local
snapshot and bridge confirmation succeed; current-company UI remains fenced.

The destination is nullable `OptionalString4000`; its maxLength uses JavaScript
UTF-16 string length. The importer normalizes an absent or null appSettingsJson
to null and otherwise does not repair malformed JSON. Preserve the exact complete
JSON when it fits 4,000 UTF-16 units. Otherwise return null and a nonsecret
`row_export_failed:company_app_settings:<uuid>:too_large|invalid_json` warning.
The existing warning prefix makes migration require user attention. Do not
truncate UTF-8, drop selected credentials, mutate server settings, widen the
column or weaken the Enterprise-secret filter. This contract concerns the
appSettingsJson path; unrelated JSON consumers retain their existing behavior.

## Ownership and validation

| Task | Owner | Writable files |
| --- | --- | --- |
| Save confirmation and regressions | directional_frontend | CompanyEfakturaSettingsForm.vue; efakturaSettingsWizard.test.ts |
| Export and PHP regression | directional_backend | ServerToEvoluMigrationExportService.php; InvoicingMigrationExportTest.php; PublicConfigTest.php |
| Milestones, integration and publication | main | This note; PROJECT_STATE, ROADMAP, WORKSPACE and DECISION_LOG |
| Independent review | QA and security | None; read-only source and disposable synthetic tests |

Verify A/B navigation in both secret-marker directions, shared/split modes,
replacement/removal, rejected echoes and another pending save. Export tests cover
fitting/exact/oversized JSON, multibyte/supplementary characters, escaping,
explicit warnings, source preservation and secret isolation. Retain existing
regression suites, full lint/type ratchets, locale parity, PHPStan/Pint, API
freshness and production build. Use only the verified network-disabled disposable
PHP target and existing frontend dependencies. No install, provider operation,
owner-data migration, merge or deployment.

## Final dispositions and independent acceptance

All three supplied findings remain valid and are fixed; none is skipped. The
additional PHP failure is also reproduced and corrected. Frontend reproduction
has five failures among 113 wizard tests before the fix; the corrected selection
passes 144 tests in three suites. Backend reproduction has eight failures among
11 selected cases before correction; migration/public-config suites then pass
19 tests / 208 assertions with the exact JSON response assertion retained.

`DirectionalSaveConfirmation` captures immutable scalar expectations before the
first await. Confirmation no longer reads the current company's shared mode or
secret marker. Captured-company cleanup still validates its submitted local
snapshot and marker; navigation does not unlock or mutate a newer company save.
Replacement/removal and invalid response echoes retain their previous behavior.

The exporter uses a settings-specific complete-JSON encoder. It measures UTF-16
units after JSON encoding, preserving the exact fitting representation including
Unicode and escaping. Oversized/unencodable settings become null with the
recognized company/reason warning. Server settings are unchanged; fitting own
SAPI secrets survive and Enterprise private fields remain excluded. Other JSON
helper consumers are outside this correction and remain unchanged.

Independent QA `efaktura_remaining` and security `directional_security` accept
the five frozen source/test files; no blocking findings remain. Backend aggregate:
`7fd24c014409a3053fa1bfac065891bf42d282a3c9e718471cbf814f54fdc22d`.
Frontend aggregate:
`a742ebaa063546c930f12b038b46c77fba2770b2f75866a6be71fdb1f29cde2a`.
Hashes are over sorted sha256sum-style path entries. The documentation milestone
is separately verified against fresh PR metadata, primary/scoped Git state and
the baseline CI result; historical prepublication entries remain attributed.

Main validation passes:

- 304 PHP tests / 2,223 assertions, including eFaktura, settings, migration export
  and public configuration. One inherited PHPUnit deprecation remains.
- 329 frontend tests in 15 suites, including the receiving wizard and destination
  snapshot validation.
- Full lint: zero errors / 46 inherited warnings. Typecheck: 110 tolerated, no new.
- PHPStan: zero errors; Pint: three changed PHP files pass.
- Five-locale parity, API export freshness, three OpenAPI schemas and whitespace.
- Production build: 7,669 modules with existing chunk-size warnings.

Independent QA passes 19 PHP / 208 assertions, 149 frontend / five suites, two
private navigation probes and six private destination/warning probes. Security
passes 19 PHP / 208 assertions, 136 frontend / three suites, and an additional
invalid-UTF8 credential probe that verifies null, nonsecret warning and unchanged
source. Counts overlap. The PHP preflight independently verifies PHP8.3.31,
testing, SQLite memory, array cache/session, synchronous queue, no cached config,
network-disabled read-only source/vendor and temporary runtime paths. No owner
environment or provider credentials are used.

The existing local CodeRabbit CLI was considered and attempted with
`review --agent --uncommitted`; it exits 1 without a structured review. It is not
counted as acceptance. No installation, login, settings change or credit override
is attempted. Graphify baseline queries preceded targeted reads; the isolated
checkout has no graph and no rebuild is performed.

## Publication checkpoint

The corrective result is independently accepted locally over the published PR447
review head. Main owns the normal existing-branch publication, exact remote-head
verification and new-head CI. No green new-head CI, thread resolution, merge,
deployment or statutory production readiness is inferred from the local result.
The owner retains merge/deployment. Final publication/CI evidence is recorded in
the primary coordination state after observation.
