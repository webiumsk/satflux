# Local expense PDF upload repair

## Accepted scope and evidence

The owner reports an individual expense attachment upload failing because an
Evolu company identifier reaches the PostgreSQL UUID company binding. The owner
confirms the individual form, not bulk PDF/ZIP import. Current reviewed/published
base is `79da6582`, PR #426. Source publication does not establish deployment.
The dirty primary checkout and the immutable import recovery snapshot are retained.

Graphify was queried from the primary graph before exploration; its broad routing
nodes provided limited useful edges. The feature worktree has no graph. Do not
install or rebuild one. Targeted reads confirm the attachment composable always
uses company-scoped detection/extraction, and detail refresh/application also
falls back to server GET/PATCH after local operations. The explicit company binder
queries before validating UUID syntax. No merchant PDF or production data is used.

## Contract before implementation

- Add authenticated, verified-email and existing business-plan-gated endpoints at
  `/api/invoicing/ephemeral/expenses/detect-isdoc`, `/extract` and
  `/isdoc-extract-quota`. Apply bounded throttling. They accept no company, expense
  or user identity and create no company, expense, attachment or durable file.
- Reuse the existing embedded ISDOC parser, file validation and user-scoped quota.
  Detection does not consume quota; successful explicit extraction does. Local
  audit metadata omits company/document/file identity. No AI/OCR or parser fallback.
- Keep existing company-scoped routes and ownership checks for server mode.
  Reject malformed company UUIDs with 404 before issuing company queries.
- In local mode, check the existing 384 KiB attachment cap before transmission.
  Ask before temporarily sending a file for ISDOC detection; cancellation keeps
  the attachment available for local storage. Confirm extraction separately.
- Store attachment content and resulting expense edits only through Evolu.
  Detail refresh uses the local resource. Missing local context fails closed.
  Guard operation completion by company, expense and pending-file identity; stale
  responses after switching/removing/unmounting cannot affect a new target.
- Leave bulk PDF/ZIP import and payment services/routes/policy unchanged. The
  unsupported local company-bound pack purchase action must never send Evolu IDs;
  hide its purchase controls in local mode while retaining quota/credit display
  and attachment-only use. Server-mode purchases retain existing behavior.
- New bridge routes require a compatible backend; older servers fail normally
  and preserve the pending local attachment without a company-route fallback.

## Ownership and gates

| Task | Owner | Writable files | Review evidence |
| --- | --- | --- | --- |
| Backend bridge and UUID binding | Backend specialist | New ephemeral expense controller; `routes/api.php`; ISDOC quota service; company binding in AppServiceProvider; new bridge tests; scoped binding tests | Auth/plan/tenant/validation/quota/no-persistence tests on a preflighted disposable target |
| Frontend integration | Main | Attachment composable; expense form/detail; ISDOC modal; API client; focused frontend regressions; five locales | Local/server, consent/skip, size cap, stale response and encrypted save/refresh cases |
| Shared integration evidence | Main | Generated API contracts, five user guides, changelog and project state | Existing contract, locale, lint/typecheck/build gates unchanged |
| Independent security | Security specialist | None | Design acceptance, final frozen-source trust-boundary review |
| Independent QA | QA specialist | None | Independent reproduction and final contract/regression validation |

No specialist commits, pushes, merges or deploys. Main publishes only reviewed
scoped changes to the authorized Satflux branch. Merge and deployment remain
separate owner gates. Tests use synthetic files and verified disposable storage;
no production database, real merchant file, wallet or credential is used.

## Implementation and review evidence

Backend implements only the three agreed routes, the nullable audit company
identifier and early company UUID guard. Author checks pass 47 PHP cases / 323
assertions, six-file Pint and scoped production PHPStan. An old-provider overlay
fails all three new no-company-query regressions; the corrected provider passes.
The isolated target is rechecked as testing, actual SQLite memory, no DB URL or
cached configuration, array drivers and no networking. An initial PDF-generation
test required the existing Dompdf driver in that disposable environment; no
dependency or product configuration is changed.

Frontend uses company-free routing only in local mode and retains existing
server routes. A native confirmation precedes plaintext detection; extraction
has its separate existing confirmation. File and draft updates remain in Evolu.
The new form persists attachments before navigation and retains its created
expense on retry. Completed attachment writes are removed incrementally, so
retrying a partial failure does not duplicate successful files. Busy form guards
prevent later file selection from missing an in-progress save's attachment set.

Independent review found SEC-PDF-1 in the first draft: the local API-shaped expense
does not expose `company_id`. Correct ownership checks use the actual Evolu
expense row's ID and company ID. The integration fixtures now use the real
`evoluExpenseToApi` adapter, with a foreign-company-row negative control. Both
local and server form continuations also check their captured route generation.

The two new frontend files pass 32 focused cases and warning-free test lint.
They cover consent/decline/skip, local/server control, missing context, size cap,
stale company/expense/file/unmount results, no server fallback, partial retries
and the deferred-save file-selection guard. Independent security accepts all
14 production files with no remaining blocking finding; its separate checks pass
32 frontend cases and 38 PHP cases / 269 assertions. The sorted relative-path
SHA-256 manifest is
`70fd0a1ee0e34b06694dc2f6ac8dd736eac9166b88fbc94461e7b811d2d45a6b`.

Five locales and five user guides describe the local attachment/ISDOC workflow.
Locale parity, production build, full lint (48 existing warnings under the same
52 limit) and typecheck (112 existing diagnostics, no new fingerprints) pass.
The combined PR #426 changelog fragment still validates within the nine-fragment
inventory. No baseline is regenerated or warning threshold raised.

Only the route inventory changes in the generated API artifacts: 450 existing
records and all existing source hashes are byte-equivalent; exactly three new
excluded routes are generated from the live isolated Laravel Router using the
exporter's existing metadata/JSON algorithm. Available local vendor dependencies
lack optional Scramble, so the locked-dependency CI must verify the full export
and full-project static analysis. Existing selected OpenAPI schemas are unchanged.
Final independent QA, full frontend evidence and published-head CI are recorded
before completion; no runtime deployment is inferred from these local checks.

Final independent QA accepts the same 14-source manifest, with no blockers:
32 focused frontend cases, 13 independent mocked-HTTP cases using the real API
URL builders and 47 PHP cases / 323 assertions pass. It independently verifies
the actual isolated database/configuration, runtime middleware, all 450 retained
route records and the three new excluded rows. The final busy-selection case
checks both single and multiple file events during deferred attachment encoding.
Security renews acceptance after that one-file guard and independently passes
the final 32 frontend cases; all other production hashes remain unchanged.

Fresh full-project PHPStan reports only the local vendor environment's missing
`Dedoc\Scramble\Scramble` class in the unchanged documentation exporter. No other
diagnostic or unmatched baseline entry is reported. Do not suppress that error
or install a replacement class; full locked-dependency CI remains the final gate.

Coordinator full frontend validation on the final source passes 1,205 cases in
151 files, zero failures, with two workers. The structured JSON report and process
exit verify the count; an earlier run with startup-only output is not counted as
evidence. The final production manifest remains identical after all checks.
