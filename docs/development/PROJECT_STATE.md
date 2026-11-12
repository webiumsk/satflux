# Project state

## PR441 review corrections accepted - 2026-11-12

All fourteen owner-supplied findings are verified against published base `f29c272f`
and corrected in the existing receiving publication checkout. No finding is skipped.
The base has successful exact-head CI43230739651 and full frontend2849/232 evidence.
Corrections cover pagination/test timestamps, usable retained originals, separate
row-locked import/ACK fences, custom provider fallbacks, effective URL secret
preservation, local credential/bridge/company guards, stable date-only display,
financial-lock-free HTTP and independent inbox health, plus fsync cleanup.

Independent final QA passes PHP84/725, frontend113/8, Python15, actual PostgreSQL2,
Apia/LosAngeles date cases, API export and three OpenAPI validators. PostgreSQL
updaters wait on the company row while ACK holds it; an independent connection
already sees the committed original. Provider HTTP remains simulated. Security
accepts final24source/test files (`e862291c`), with independent JS11/Python15 and
old-source regression failures. Main final typecheck110/no new, lint46/52,
Pint11/PHPStan5 and locale/widget/whitespace checks pass. The final full frontend
run passes2,872tests/233files and isolated build passes7,659modules. Corrected-head
CI is recorded separately in the publication handoff.

See `EPOSTMAN_PR441_REVIEW_FIXES.md`. Existing runtime APIs/schema/locale keys and
owner preview/account remain preserved; publication continues on PR441 without
master merge or production activation. The optional CodeRabbit CLI review did not complete; independent QA/security
provide the review evidence.

## ePostman publication prepared against current master - 2026-11-12

The owner-authorized receiving and customer onboarding branch is published as
[draft PR441](https://github.com/webiumsk/satflux/pull/441). Exact-head CI remains
pending; publication does not activate production. An isolated publication worktree on
`feat/efaktura-epostman-receiving` integrates `origin/master` at `07c1af23`.
Only the three shared state documents conflict; both histories are retained.
The existing `feat/efaktura-epostman-inbound` checkout and manual localhost account
remain preserved. Publication and exact-head CI are tracked separately from
production activation; provider archive/export/contract remain outstanding.

## Guided ePostman customer onboarding accepted locally - 2026-11-12

Owner confirms the localhost connection works and authorizes guided customer
setup. The existing feature worktree now offers built-in production/test choices,
fixed endpoints, activation and SAPI-client instructions, derived identity guidance,
truthful saved/unsaved/check/poll status and Expenses navigation in all five locales.
Each company keeps its own provider account and credentials; delegated one-click
consent remains unconfirmed. Sending and backend/billing contracts are unchanged.

Independent QA/security accept the twelve-file onboarding scope. Failed/interrupted
bridge synchronization persists in encrypted local settings and remains visible
after reload, including a failed disable. Credential host switches discard old
inputs and late responses cannot alter another company's view. QA76tests/6files,
final wizard35, two independent persistence cases and Chrome24 synthetic UI checks
pass. Browser API/Evolu boundaries are simulated; earlier actual transport/custody
proof remains separate. Full lint46/52, typecheck110 inherited/no new, locale parity,
widget provenance and final isolated production build7,636modules pass.

Source remains local feature `f01f7d5b` plus reviewed earlier parser/inbox fixes and
this onboarding work. Security manifest `13d17b52` matches all twelve files. See
`EPOSTMAN_CUSTOMER_ONBOARDING.md` in the feature worktree for exact hashes/evidence.
The owner's existing localhost account/data remain preserved and preview is running;
only the separate QA harness was stopped. Manual preview polling remains necessary
because it has no scheduler. No commit, push, deployment or new provider operation.
Production activation and provider archive/export/contract remain outstanding.

## Manual eFaktura localhost preview ready - 2026-11-12

Owner requested a usable localhost test. The separate normal application preview
at `http://127.0.0.1:8082` uses the ePostman worktree, its own SQLite/key/sessions
and a synthetic Pro account. Two recovered copies of the owner's acknowledged
test Invoice/CreditNote are pending for a new local handoff, with original XML,
digests and provider provenance retained. Retrieve the test login locally with
`bash storage/app/private/.env.efaktura-local/show-login.sh`.

Independent no-mock browser QA passed normal consent/login/recovery/phrase unlock,
server-company import, two actual HTTP inbox imports, original download/digest and
signed totals, guest 401, missing-CSRF 419, foreign-company denial, duplicate confirmation and
reload persistence. The accepted run has zero page errors or blocked external
requests. Full Chrome restart was not repeated here; earlier isolated custody
restart evidence remains separate. Provider polling was not invoked during QA.

QA found a real inbox projection defect hidden by previous settings-bearing list
mocks. Reusing the existing detailed company query fixes it. The regression fails
on old source. All 41 tests in 5 files, scoped lint, typecheck within the inherited
110-error ratchet, locale parity and an isolated build pass. Security accepts the
exact runtime/test pair and private preview configuration.
After QA, only preview PHP was stopped to retain post-QA evidence and restore its
pristine snapshot. Own sessions/cache were cleared and PHP restarted. Human
handoff again has two pending originals and no QA recovery key. Existing
`localhost:8080` uses its separate migration preview. Source remains local `f01f7d5b` plus the reviewed
parser and inbox-query corrections. Archive/contract and production remain pending.

## Actual cross-AP CreditNote and signed-total correction verified - 2026-11-12

The owner's Recommand TEST CreditNote was retrieved through the actual ePostman
client and staged encrypted before real ACK in a fresh isolated persistent
database: imported1, acknowledged1, failed0. Independent XML amount inversion
verified a negative local expense; retry, reload and full Chrome restart preserved
one expense/attachment and identical original bytes. BillingReference matches the
previous test invoice exactly and remains in the original XML; structured expense
linking is not implemented. Actual backend markImported passed twice, then staging
was cleared. Browser HTTP was intercepted as in the earlier invoice test.

Inspection also found a valid negative-valued CreditNote sign defect. The bounded
one-line parser correction preserves the source sign before inversion, with six
regression cases. Author26tests/174assertions plus scoped Pint/PHPStan pass;
independent QA11tests/25assertions pass and independently reproduce two original
failures. Independent security accepts the exact two-file fix and six additional
synthetic parser cases. The corrected parser also reparses the actual CreditNote
successfully. Feature source is local `f01f7d5b` plus this uncommitted correction;
primary work and the other localhost preview remain preserved. Private evidence
is under `storage/app/private/reviews/epostman-inbound/live-creditnote`.
Provider archive/export/contract and production activation remain unverified.

## Actual cross-AP sandbox invoice received - 2026-11-12

The owner created a new Recommand playground explicitly using Peppol Test Network
and sent a synthetic invoice to the existing ePostman sandbox participant. The
real Satflux SAPI client listed one document and retrieved its original XML.
The unchanged feature poller then staged the original encrypted in a separate
persistent SQLite database and received one actual provider ACK, with zero
failures. A separate SQLite connection validated committed original bytes before
ACK; encrypted recovery bytes and the test key remain private and durable. A new
container confirmed one pending receipt and preserved staging. Independent actual
Chrome/Evolu QA imported that XML with one expense/attachment, matching amount and
currency, first-confirmation failure/retry without duplicates, exact bytes after
reload and full Chrome restart. The actual backend markImported transition then
passed twice with the protected matching digest, leaving one imported receipt and
cleared staging. Browser HTTP was intercepted; real provider and final backend
service transitions were tested separately. Main localhost preview and production
are unchanged. Earlier direct DNS NXDOMAIN observations did not
establish the cause of the initial failure; actual cross-AP delivery supersedes
the discovery blocker below. Source remains local feature `f01f7d5b`. Independent
security accepted the isolated helpers after filename/durability hardening. Private
evidence is under `storage/app/private/reviews/epostman-inbound/live-sandbox`.
Credit-note interoperability, provider archive and production remain unverified.

## PR #440 review corrections - 2026-11-12

GitHub verifies open PR #440 against master `bae0295f`, reviewed head `b19e8fc4`.
All four supplied findings remain valid: clarify original XLSX handling in five
locales, clear canceled scan progress in `pickFile`, isolate the extra-original
mock, and commit a standalone real PDF.js byte-to-invoice browser regression.
XLSX validation, financial import, authority and encryption code are unchanged.

Local validation: 156 focused tests, two real-worker browser cases, scoped lint,
five-locale parity, full typecheck (unchanged 110 baseline) and production build
pass. The corrected extra-original test independently fails when its intended
guard alone is removed in a private mutation. Independent core and harness
reviews pass with no open Critical/High/Medium findings. The real-worker test
also fails under private reader/parser mutations. Next: inspect fresh PR CI/review
results on the existing authorized feature branch; no merge or deployment is
authorized.

## iDoklad branch pushed for owner PR - 2026-11-12

The owner authorizes pushing `feat/invoicing-idoklad-migration` to the owned
Satflux repository. Source commit `1e44995a83f0b4ee315bb54d730f7d6a4fd159c9`
is pushed and verified on the remote feature branch. Fresh remote master remains
`bae0295f`, the implementation base. The owner retains PR creation; no PR,
merge or deployment was performed. Remote CI results have not been inspected.

The source commit contains exactly 28 reviewed paths. All bytes match the final
security/QA candidate except the official SVG's CRLF-to-LF normalization, whose
canonical XML content is identical. Staged whitespace validation passes. Prior
474-test, lint/typecheck/build/locale and independent security/browser evidence
remains applicable; product tests were not repeated for line endings or this
publication record. Local preview settings, dependency links, private source
files and unrelated primary-checkout edits remain excluded.

Next step: owner opens the PR; inspect fresh CI/review findings before merge.

## iDoklad XLSX + original PDF available in local preview - 2026-11-12

The owner supplied all four matching native Slovak PDFs and the official logo.
Read-only source proof now reconciles four XLSX records with five original lines,
including nonzero VAT, full payment and a partial payment with unknown date.
The PDFs contain native text rather than embedded ISDOC. The dedicated iDoklad
card is enabled in the existing local preview on `feat/invoicing-idoklad-migration`,
based on merged master `bae0295f`; no owner invoices were imported by the agent.

The bounded XLSX/native-PDF adapter preserves exact net/VAT/gross, per-line
rounding, original bytes and fresh company authority at commit. Missing or
inconsistent originals block the batch; no summary fallback invents VAT lines.
The supported profile requires company buyer registration and complete native
single-page invoices. CSV and other PDF templates remain outside V1.

Final integrated regression passes 474 tests; independent security passes 430
tests with no open Critical/High/Medium findings. Isolated browser QA verifies
exact originals after reload/full restart, payment semantics, retries, rejection
and account/company/source cancellation. The worksheet dimension/relationship
omission paths and canceled-scan progress label are corrected and rechecked.
Full lint and five-locale parity pass. Final typecheck passes the unchanged
110-error baseline and production build passes after all corrections.
Earlier source-assessment entries below are historical. No source publication or deployment is claimed. See
[IDOKLAD_MIGRATION.md](IDOKLAD_MIGRATION.md).

## Updated iDoklad workbook proves payment fields; item evidence pending - 2026-11-12

The owner supplies a second XLSX with 22 columns and four records, including
actual paid amounts and a full-paid payment date. Read-only checks confirm full,
partial, unpaid and overdue-unpaid cases, valid dates, bounded paid amounts and
exact net + VAT = gross; all four records have nonzero VAT. Partial payment date
remains unknown. Buyer identifiers are now present. Original item details and
VAT rates remain absent. The source card stays disabled pending detailed source
evidence. The owner confirms only XLSX/CSV export is available. Official Czech
documentation advertises embedded ISDOC in original PDF invoices; one actual
provider-generated PDF has been requested to verify the Slovak-account format.

No product code, financial writes, provider access or publication occurred.
The prepared `feat/invoicing-idoklad-migration` branch still starts at fresh
master `bae0295f`. See [IDOKLAD_MIGRATION.md](IDOKLAD_MIGRATION.md).

## iDoklad workbook inspected; detailed evidence pending - 2026-11-12

The owner supplied an XLSX invoice-list export. Main read-only inspection finds
three records (unpaid, partial and overdue unpaid), with native Excel dates and
a row-5 header. Gross/net totals are present; original items, VAT rates, actual
paid amounts and payment dates are absent. A generic summary import would lose
the known net/VAT distinction, so the iDoklad card remains disabled. Matching
Pohoda XML has been requested; no payment amount or item breakdown is invented.

New branch `feat/invoicing-idoklad-migration` starts from freshly fetched master
`bae0295f`, preserving existing preview settings and prior documentary notes.
An independent read-only explorer confirms header/date and preview/commit
extension points. No product code, owner invoice, provider access, publication or
deployment changed. See [IDOKLAD_MIGRATION.md](IDOKLAD_MIGRATION.md).

## Fakturoid merge verified; next adapter proposed - 2026-11-12

GitHub confirms owner-merged PR #439 at
`bae0295f9c071f37eb219b65129bd45ed4b0b080`, with feature head `dfe7c419`.
This verifies the merge only, not a production deployment. The local preview
checkout remains on the retained feature branch; unrelated primary work and
local-only preview files are preserved. The PR-linked Fakturoid changelog entry
remains outstanding follow-up work.

iDoklad is the remaining agreed source after SuperFaktura, Zaprite and Fakturoid.
Read-only official-source research identifies XLSX/CSV list exports and direct
Pohoda XML export. Propose testing both from the same synthetic unpaid/partial/
paid invoices before choosing the source/payment contract. No iDoklad account,
export sample, parser implementation, API access or deployment is claimed.
Start any implementation branch from freshly fetched master after confirming
scope and obtaining authoritative sample evidence.

## Fakturoid branch published for owner PR - 2026-11-12

The owner confirms the import works, accepts the local official logo and asks for
`feat/invoicing-fakturoid-migration` to be pushed to the owned Satflux repository.
The owner will create the PR. No PR creation, merge or deployment is authorized
for this handoff. Fresh remote master remains `007b9c02`, the exact feature base.

The candidate contains 20 explicit feature paths: native XML adapter, shared
import integration, source picker/logo, synthetic fixtures/tests, five locales
and four development documents. Local preview configuration, a dependency
recovery link, private owner exports and browser state are excluded. Existing
313-test regression, independent security/browser and static evidence below
remain attributed to the tested revisions. The later logo-only delta passes
scoped ESLint, Vue template compilation and isolated source-card image rendering.
Independent privacy and QA evidence binding accept the exact candidate. Source
commit `c42aa65234daa34be76f5dc032a75205883a5fc7` is pushed and verified on
`origin/feat/invoicing-fakturoid-migration`. No PR was created. GitHub CI and
merge/deployment remain separate from these local checks and this branch push.

## Fakturoid migration resumed from merged master - 2026-11-11

GitHub confirms PR #438 merged at `007b9c027c7887d59300a781e861ad09ecb94e06`.
The owner authorizes continuing with Fakturoid. New branch
`feat/invoicing-fakturoid-migration` starts from that freshly fetched master in
the existing clean preview checkout, preserving its local-only settings and the
primary checkout's unrelated work. Merge evidence is not a deployment claim.

The owner subsequently supplied genuine native XML. Read-only inspection verifies
the initial two unpaid invoices and three items, then an updated four-invoice/
five-item sample with actual partial and full payment fields. The local preview now offers a
Fakturoid XML adapter with exact finance, fresh issuer binding, shared duplicate
checks and immutable original custody. The verified payment expansion uses exact
`paid_amount` with a null partial date and actual full date; coverage fields never
become balances. Final payment-specific regression/security/static and isolated browser gates pass.

Final 13 migration/import/backup suites pass (313 tests), plus separate independent
payment security review (232 tests, no open Critical/High/Medium). Full typecheck adds no errors to
the 110 baseline, full lint passes with 48 inherited warnings, and locale parity
and production build pass. Independent browser QA passes unpaid detailed import,
unsupported/company rejection, duplicates/conflicts, six load/confirm ABA races
and delayed-error suppression. Reload/full restart preserve exact XML/PDF
downloads and records, with zero page errors/backend business writes. A separate
paid browser follow-up verifies four invoices/five lines, exact partial/full
amounts/dates, overpayment blocking, changed completed amount/date conflicts and
exact XML custody after a full restart. No owner invoices
were written by the agent. No new feature publication or deployment is claimed.
See [FAKTUROID_MIGRATION.md](FAKTUROID_MIGRATION.md).

## Feature published for GitHub review - 2026-11-11

The owner explicitly authorizes pushing `feat/invoicing-action-center-migration`
to the owned Satflux repository. Freshly fetched master remains `6e6c0587`, the
feature base. The initial published commit `ed683ff9` contains 124 feature files, excluding
local preview configuration and a local dependency recovery link. The primary
checkout's unrelated work is preserved.

Final complete frontend verification passes 2,540 tests / 225 files, with zero
failures, skipped tests or todos. Combined backend verification passes 80 tests /
665 assertions across seven suites in guarded disposable SQLite with the
configured dompdf driver. Pint passes 17 PHP paths, scoped PHPStan passes 11
runtime paths, and API contract/inventory freshness passes. Final full typecheck
passes the unchanged 110 baseline and the production build passes. Full lint
passes with zero errors and 48 warnings within the unchanged limit of 52. Changelog
71 tests, FAQ/catalog, OpenAPI validation, production critical-audit gate and
locale parity pass. The new dependency nodes have no reported advisory;
existing dependency advisories remain attributed in the private review.

Independent publication privacy review accepts the exact 124-file allowlist.
Earlier independent browser/Evolu custody and 74-path security evidence remain
linked in the feature runbook. The feature branch is pushed and
[draft PR #438](https://github.com/webiumsk/satflux/pull/438) targets master.
The reviewed user-facing changelog uses that canonical PR URL. GitHub CI is
pending observation; merge and deployment remain separate owner decisions.

## Zaprite migration accepted in the local feature preview - 2026-11-11

Owner authorized continuing with Zaprite and supplied a read-only Sandbox key.
The existing migration wizard now offers Zaprite API/JSON, detailed item and
payment preview, exact fiat totals, common duplicate/conflict protection and
complete encrypted JSON custody. An ephemeral authenticated fixed-host GET
bridge does not retain credentials or mutate Zaprite. Source bytes are parsed
again at commit before the existing company/owner, receipt and readback gates.
BTC/LBTC and unsupported currencies block by default; explicit exclusion retains
the complete source JSON without importing excluded invoices.

The actual isolated PHP client read three Sandbox invoices successfully without
financial writes. The converter matched two supported fiat invoices, preserving
the partial payment and two items each. Main regression passes 218 tests / 13
suites; final converter passes 53. Backend passes 31 tests / 151 assertions.
Typecheck passes the unchanged 110 baseline; scoped lint, Pint, build, locale
parity and API inventory checks pass. Independent security accepts 74 paths,
manifest `1fa38f5159491a672c76f0f88dfcd7d54a779db5858a7710933a880c75ba571d`.
Independent Chrome/Evolu QA verifies explicit exclusions, API pagination,
ABA cancellation, duplicates/conflicts and exact source/item/payment persistence
after reload and full browser restart. See the feature runbook for evidence.
Status remains localhost preview on `feat/invoicing-action-center-migration`.
The owner subsequently confirmed that API import works. The saved-JSON label
now identifies Zaprite as the data source and this wizard as the file producer.
This is copy clarification only; the accepted JSON envelope is unchanged.
The agent did not import into the owner's company, publish or deploy during
local acceptance.
The owner-supplied Zaprite logo is bundled locally. Final bounded QA passes
57 parser/date tests and a separate fresh accessible source-card browser check.

## Local company and Profile loading corrected in the feature preview - 2026-11-11

Normal browser entry reproduced a blocked company list and Profile statistics
despite answered local company queries. Passive fingerprint/statistics reads
now explicitly omit migration originals while complete snapshots remain the
default. Nonempty local company lists are available before optional relay
work outside pending recovery. Profile publishes counts before relay usage
and calculates database size on explicit refresh, with stale-account guards.

Integrated regression passes 109 tests across 12 suites. Typecheck passes
the 110-finding baseline, scoped lint/parity/build pass. Independent security
accepts the source and independently passes 45 tests. Normal-entry Chrome QA
confirms the local screens load with quota refusal and withheld archives,
then verifies complete snapshot archive digests and manual database export.
See [ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).
Current status is localhost feature preview.

## Relay quota is distinguished from local storage failure - 2026-11-11

A browser-storage warning exposed two coupled errors. A relay-generated
`ProtocolQuotaError` was classified as browser storage pressure and rejected
local receipt observation despite an independently committed local write.
The local observer now excludes only that exact relay discriminator and still
requires a positive receipt and post-handler readback. Local worker errors,
missing receipts and quarantine remain enforced. The banner shows the relay
limit and its synchronization explanation.

Focused regression passes 295 tests across 15 suites. Typecheck passes the
110-finding baseline, scoped lint and locale parity pass. Independent security
accepts the source with 59 tests. Current status is the local feature preview.
See [ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## Migration storage readiness and rejected-write recovery prepared locally - 2026-11-11

The owner reported an unknown storage result after about 30 seconds. Two
independent synthetic Chrome reproductions cover an older tab holding the
previous database schema and an ignored contact validation failure discarding
the completion callback. The importer now checks schema metadata before writes
and handles known contact and audit-event rejections explicitly. Unknown
storage results carry finite phase/reason diagnostics and require reopening
the browser clients before another attempt. The exact cause of the owner's
original attempt remains unverified.

Focused regression passes 150 tests across 12 suites. Typecheck passes the
110-finding baseline, scoped lint and locale parity pass, and the application
build passes. Current status is local preview on the existing feature branch.
See [ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).

## Signed SuperFaktura deductions verified on the local migration branch - 2026-11-11

The local `feat/invoicing-action-center-migration` worktree preserves negative
unit prices as separate historical lines when ISDOC amounts reconcile.
The localhost worker read 33 originals and matched all 32 supplied workbook
rows, including six signed invoices, with no invoice writes. Independent
Chrome/Evolu QA verified custody and retry with synthetic documents. Signed
SK/CZ visual PDF generation verifies stored line gross, subtotal, VAT and total
and requires disabled payment routing. Evidence and scope are recorded in
[ACTION_CENTER_AND_MIGRATION.md](ACTION_CENTER_AND_MIGRATION.md).
Owner preview is available on localhost. No publication or deployment occurred.

## Real ePostman sandbox authentication/listing verified - 2026-11-10

Owner re-entered distinct sandbox credentials through hidden local prompts. The
reviewed read-only probe completed successfully against the fixed test origin:
authenticated=true, one receive page, zero documents. No detail/XML retrieval,
ACK, invoice sending, import or company/database write occurred. This verifies
actual sandbox authentication and receive-list access, not document delivery,
provider/archive guarantees, cross-AP interoperability or production readiness.
Next receiving evidence requires a synthetic incoming invoice/credit note in the
test inbox, followed by isolated durable import verification. Secret values remain
in the owner-only local file and were not printed or copied into reports.

## ePostman sandbox account ready; protected probe prepared - 2026-11-10

Owner reports an activated sandbox account and asks for live testing. Credentials
were shared in chat; no values were copied into tools, source, reports or agents.
Rotate that test secret and enter replacements only through local hidden prompts.
A read-only sandbox probe is prepared: fixed test origin, isolated uncached testing
configuration, protected JSON input and anonymous output, no send/ACK/import or
model/database writes. Live authentication has not run; the private credential
file is not yet configured. A missing-file launch reached the credentials stage
and stopped safely before authentication. Production state remains unchanged.

## ePostman receiving accepted locally - 2026-11-10

Owner-authorized receiving-first implementation is ready on
`feat/efaktura-epostman-inbound`, base `6e6c0587`, in durable worktree
`storage/app/private/worktrees/efaktura-epostman-inbound`. Fixed test/production
profiles support JSON /sapi receiving, complete pagination, validated company/XML
identity, immutable receipt provenance, safe original custody, visible poll health
and protected credential/settings changes. ePostman outgoing is explicitly blocked.
Local original read-back/digest confirmation and partial-import recovery preserve
Evolu encryption; no destructive pending expiry/dismiss or new permanent server
archive. Owner's separate LLC and existing generic provider behavior remain isolated.

Independent security/QA accept36 runtime/test paths, manifest SHA256
`b8296fafe97a6d7a77c57eecc9a1e83165335a8a19e6a24732e5268a4051b75f`.
Real Chrome verifies exact original bytes after reload and full browser restart,
including failed-confirmation retry without duplicates.
Locked PHP104/554 and independent67/424, frontend2325/209 plus finalwizard19,
Pint19/PHPStan/API checks, final build/type110/lint48/parity/changelog14 pass.
Full PHP is not green:3216 tests,33 errors/28 failures/69 skips; all61 failing
identities/classifications/diagnostics reproduce on unchanged base in the identical
minimal environment, independently checked by QA. No unrelated fixes or exclusions.

Evidence: feature `docs/development/EPOSTMAN_INBOUND_IMPLEMENTATION.md`; private
review material under `storage/app/private/reviews/epostman-inbound` in coordinator
checkout. Actual cross-AP receiving, provider archive/activation and production
remain unverified. Originals above384KiB stay pending/downloadable; local handoff
cannot finish for them. No provider account, paid contract, production credential,
push/PR, merge or deployment occurred. Primary unrelated dirty work is preserved.

## Slovak eFaktura public claims withdrawn locally - 2026-11-09

Owner-approved copy correction on `fix/efaktura-public-claims` from owned master
`8dfa449b` removes ready SK eFaktura delivery claims from five-language public
marketing and comparisons. Existing ISDOC/UBL export stays accurately described.
Settings/readiness warnings explain unverified integration, authentication versus
delivery and separate provider/API terms. Reserved example host replaces the
unverified service URL. Five localized status articles preserve the canonical slug;
existing controls, credentials, eligibility, histories and runtime logic remain.

Independent QA/security accept 18 source paths, manifest SHA256
`5635c72e4a167e408edb2fc70ffa19d5283125371428193c2f07f19d337ab0ba`.
Focused UI 87/9, full lint 48/52, type 110, locale parity, build, FAQ 12/5/39,
changelog 14 and whitespace pass; QA independently passes 322 assertions, compiles 110
messages and checks 8.75:1 warning contrast. No test, baseline, dependency, schema,
configuration or provider changes. Evidence: `docs/development/EFAKTURA_PUBLIC_STATUS.md`
in the durable feature worktree; private reviews under
`storage/app/private/reviews/efaktura-public-claims`.

Further Slovak eFaktura development is paused pending owner direction, provider
terms and complete verification; no completion date is promised. No PR, push,
merge, deployment, database content edit or live-provider operation occurred.
Primary unrelated work remains preserved. Source changes do not update live copy.

## FAQ PR435 Czech precedence and label-depth corrections accepted locally - 2026-11-09

Three owner-supplied findings are verified against `70d57319`: two correctness
issues and one retry-coverage gap. Per-field Czech legacy search now follows
canonical blank/trim precedence without changing English predicates. Link labels
no longer consume an emphasis level for their container; actual eighth-level
formatting is asserted. Retry tests set both filters before failure and check
request parameters and retained controls after success. No finding was skipped.

Independent QA/security accept five exact source/test paths. UI83, SQLite38/403,
actual PostgreSQL11/100, scoped lint/Pint2/PHPStan, type110/parity, catalog12/5/39,
build and whitespace pass. Independent QA adds seven label cases; security13
focused frontend cases. Both original behavioral defects reproduce on the exact
base. Existing sanitizers/URLs/depth bounds, English search and filter grouping
remain. No dependency, schema, catalog, locale-key or baseline change.
Evidence: `docs/faq/REVIEW_FIXES.md`; private `reviews/faq-automation/pr435-round5/`.
Coordinator publication and exact-head CI remain the next step. The disposable
PostgreSQL target is removed. No merge or production command/deployment ran;
unrelated primary changes are preserved.

## FAQ live locale/admin/link fixes accepted locally - 2026-11-08

Owner-reported live failures are fixed on the existing FAQ branch from `85a633b5`.
Explicit allowlisted request locale now controls public list/detail/search/category
text with restored request context. The page reloads on language changes, preserves
filters and ignores/aborts obsolete responses, errors and timers. Admin categories
sort by primary order, canonical English JSON text and id, avoiding the PostgreSQL
JSON ordering error. Deep-scoped CSS makes injected links underlined and visibly
focusable. No global middleware, auth, catalog, schema or dependency changes.

Independent QA/security accept eight exact source/test paths. UI 83, PHP 38/403,
actual PostgreSQL 6/43, additional auth/locale/race probes, PHPStan/Pint 4, scoped
lint/type 110/parity/build/API/whitespace pass. Existing Chrome offline CSS proof
confirms underline/focus and 7.36:1 contrast; no browser install or live data access.
Evidence: `docs/faq/REVIEW_FIXES.md` and private
`storage/app/private/reviews/faq-automation/live-fixes/`.
Coordinator owns source commit/push and exact-head CI verification. The owner
reports an earlier deployment; this corrective revision is not deployed by the
agent. No production command/import ran; unrelated primary changes are preserved.

## FAQ PR435 deep emphasis matching accepted locally - 2026-11-08

The supplied finding is valid against `08cf0e62` and corrected in the existing
FAQ branch. Iterative logical frames and flat output tokens preserve delimiter
ownership beyond eight generated formatting levels, consume matched syntax and
retain ordinary unmatched markers. Label parsing uses one links-disabled child
call, keeping parser calls bounded to two; URLs, sanitizers and scan caching remain.

Independent QA/security accept two exact component/test files. Focused 68, QA 31,
security 18, actual 60 answers/75 links, scoped lint/type 110/parity/build/whitespace
and local JS documentation 9/9 pass. Deep 10,000-layer and malformed-input probes
pass. No backend, catalog, fingerprint, dependency or ratchet changed. See
`docs/faq/REVIEW_FIXES.md`; private evidence is under
`storage/app/private/reviews/faq-automation/pr435-round3/`.
Coordinator owns commit/push and exact-head CI verification. No merge, production
import, FAQ publication or deployment occurred; unrelated primary work is preserved.

## FAQ PR435 second review corrections accepted locally - 2026-11-08

All four owner-supplied findings are valid against `cc60c4b8` and fixed on the
existing FAQ branch. Complete opening-bracket labels match validator grammar;
links remain clickable throughout depth-capped input, without deeper recursion or
quadratic malformed-label retries. PHP/Node accept finite whole decoded JSON
numbers in 0..2147483647 consistently, with PHP integer normalization. The real
--check command test now uses default catalog paths with an unavailable database;
wrong-path and database-read mutation tests prove the regression assertion works.

Independent QA/security accept seven exact source/test paths. PHP 33/185, UI 52,
Node 60, unchanged catalog 12/5/39, PHPStan/Pint 3, scoped frontend lint/type 110/
parity/build pass. Independent raw-number and renderer attack/performance probes
pass; local documentation inspection is 45/45 PHP and 9/9 JS qualified functions.
No dependencies, thresholds, baselines or catalog fingerprints changed. Evidence:
`docs/faq/REVIEW_FIXES.md` and private `reviews/faq-automation/pr435-round2/`.
The coordinator owns corrective publication and exact-head CI verification;
prior-head CI/CodeRabbit results are historical. No merge, production import,
FAQ publication or deployment occurred; unrelated primary work is preserved.

## FAQ PR435 review corrections accepted locally - 2026-11-08

All six owner-supplied findings were valid against `8242c351` and are corrected on
`feat/faq-automation` for existing open PR435. Search predicates live in the public
controller rather than the model referenced by the review. Omitted category keys
retain assignment; explicit null clears. Czech search includes legacy `cz` only for
`cs`. PHP/Node source checks require per-item available translated counterparts and
reject order overflow beyond 2147483647. Bounded Markdown parsing restores nested
links and safe legacy HTML while retaining two sanitization passes and local URLs.

Independent security and QA accept exact corrective source manifests. PHP 30/136,
actual PostgreSQL 4/23, frontend 43, Node 20 and independent renderer/source attack
cases pass. PHPStan, Pint 9, scoped lint/type 110/parity/build/API checks pass. Local
AST/token coverage is 42/42 PHP + 8/8 JS qualified named functions with docstrings;
no external post-push CodeRabbit coverage pass is inferred. New PostgreSQL CI
coverage uses a separate test database and rejects skipped/error/failed JUnit cases;
the exact step passed locally. No thresholds, baselines or dependencies changed.

See `docs/faq/REVIEW_FIXES.md`; sanitized review records remain private under
`storage/app/private/reviews/faq-automation/pr435-corrections/`. Earlier broad PHP
suite/environment limitations remain historical; no new whole-suite pass is claimed.
Catalog 12/5/39 and its fingerprints are unchanged. No merge, production import,
FAQ publication or deployment occurred; unrelated primary work remains preserved.
Coordinator owns the corrective commit/push and exact-head remote verification.

## Multilingual FAQ automation accepted locally - 2026-11-08

Owner-approved FAQ automation is implemented on `feat/faq-automation` from owned
master `f7118b10`, isolated in `storage/app/private/worktrees/faq-automation`.
Twelve questions across four categories have EN/SK/ES/DE/CS text (60 variants),
75 safe documentation links and 39 reviewed source fingerprints. Offline CI checks
reject incomplete translations, unsafe links and changed documentation sources.
No AI provider or dependency installation is introduced.

`faq:sync --check` needs no database; default invocation previews. Explicit staff
attribution and `--apply` create drafts transactionally. Stable keys and baseline
hashes protect manual edits and unmanaged slug collisions; publication state,
counters and extra locale text are preserved. Nullable metadata migration never
imports/publishes content. Canonical Czech uses `cs` with preserved legacy `cz`
fallback. The editor retains omitted languages; public answers allow sanitized
local documentation links and expose accordion keyboard state.

Independent security and QA accept final 23-path implementation scope. Focused
PHP22/115 plus independent real-catalog4/36, UI22 and Node8 pass. Full frontend
2,231/205 passes with two workers after an earlier contention-limited run.
Type110, lint48/52, locale/build, scoped Pint/PHPStan and API-contract checks pass;
nine obsolete FAQ baseline entries are removed with no new suppressions.
The broader PHP run completed with 27 errors/11 failures across 3,169 tests.
All 32 bank cases pass after supplying the existing compatible Node/runtime; six
DNS-dependent e-mail failures reproduce on unchanged master without networking.
No clean whole-PHP-suite rerun or remote CI result is inferred. Full evidence and
limits are in `docs/faq/VALIDATION.md`; sanitized independent records stay private
under `storage/app/private/reviews/faq-automation/`.

Existing legacy-only Czech search remains limited to canonical Czech/English;
new imported entries have `cs`. Some documentation translations use English
fallback. PostgreSQL concurrency and real-browser checks were not executed.
No production import, publication, merge or deployment occurred. Primary unrelated
changes are preserved. Next step: review source changes, then separately approve
normal deployment/import and editorial publication through existing admin tools.

## Per-relay protocol status and Freedomrelay defaults - 2026-11-07

Owner requests separate connection/synchronization status and chooses Satflux plus
Freedomrelay defaults after compatibility verification. Both public endpoints reply
with wire1 in a read-only version challenge; no key, subscription or data is sent.
This does not establish quotas, write acceptance, retention or uptime. Freedomrelay
publishes fair-use/no-uptime terms without a numeric cap; free.evoluhq.com remains
a testing service whose data may be deleted and is no longer automatically offered.
Explicit saved lists/order/off flags and legacy custom-only selections remain exact.

Worker observation uses bounded public SDK header primitives, internal owner-ledger
isolation and finite origin/status/timestamp output. Socket-open and protocol quota
are separate. Only a fresh complete same-owner empty reply clears its error;
unrelated owners/reconnect/partial replies cannot. A reply never proves full backup
or replay of previously rejected history. Profile current warnings use live guarded
protocol evidence; global SDK errors are explicitly historical. Offline paused
status leakage is independently reproduced then fixed. Prior authority/SDK limits
remain; the old confirmed keyless default exception is preserved only in its prior
scope, including build/device-off/account guards.

Security35/4 accepts runtime33 `1feebae8...47cc16e`; final source65
`6626058d...da868aa` differs from tested source only in six reviewed text paths.
QA115/6, actual SDK/SQLite1 and final-CSS browser12 pass; provider proof is version
only. Main frontend2,207/204, PHP119/767, type110, lint48/52, locale/build,
Pint/PHPStan2, API3/changelog11 and7 rendered guides/12 links/3 fallbacks pass.
Local AST documents108/108 qualified named functions; no external pass is inferred.

PR432 is open/unmerged at reviewed base `6e747b30`. Main owns scoped publication
and fresh exact-head CI verification; merge/deployment remain separate gates.
Private sanitized evidence: `reviews/evolu-custom-relay/protocol-status/`.
Unrelated primary work is preserved; no dependency, baseline or production-data change.

## PR432 recovery enrollment CI correction - 2026-11-07

CI42353889750 on `4fab39c6` failed only the unchanged recovery-enrollment E2E:
a Profile wizard opened for A submitted refreshed account B (expected409, got200).
Independent browser reproduction confirms the failure. The wizard now preserves
its opening account/flow; stale completion cannot bind local phrases or update a
later flow. Optional current-context guards stop new owner/snapshot work after
invalidation, preserving existing callers. Already submitted work cannot roll back.

Independent security29/4 accepts runtime32 `74de89b0...5fa619` / source62
`49872677...6499c9`; QA84/7 focused checks pass. Every frozen byte matches.
Main lint48/52, type110, locale parity and CI-flags production build pass.
Main frontend2,179/203 and browser24pass/3existing conditional skips pass without retries.
Original409 and subsequent enrollment/restore/logout checks pass unchanged. Main owns
scoped corrective publication and new-head CI verification.
PR432 is open/unmerged; merge and deployment remain separate pending owner gates.
Evidence: `EVOLU_RELAY_MANAGER.md` and private `reviews/evolu-custom-relay/ci-recovery/`.

## PR432 relay origins and canonical cache review - 2026-11-07

Both outside-diff findings reproduce on published `b84d82a6` and are corrected.
Equivalent IPv6 origins normalize before list uniqueness/persistence; accepted
full-IPv4 trailing-dot aliases normalize consistently too. Cached selections never
authorize startup/restored owners without a fresh canonical account read. Unknown
or stale responses remain paused; device-off and account/read ordering are retained.

Reviewed runtime30 `40db84da...b8d22d` / source57 `c29b6fa7...92a622` match the
independent security/QA scope before and after checks. Security20 integration cases
plus900 origin/90 duplicate comparisons pass; QA16/3 including actual SDK/SQLite
and auth races passes. Main frontend2,156/202, PHP110/737, type110, lint48/52,
locale/build, scoped Pint/PHPStan, API/changelog11 and7 rendered guides pass.
Local AST documents91/91 changed complexity-qualified named functions (81 runtime);
this is local evidence, not a new external docstring coverage result.

PR432 remains open/unmerged; review base is `b84d82a6`. Main owns scoped corrective
publication and exact-head CI verification. Merge and deployment remain separate
pending owner gates. No dependencies, ratchets, billing or unrelated primary work
change. Contract, review limits and private evidence: `EVOLU_RELAY_MANAGER.md` and
`reviews/evolu-custom-relay/cache-confirmation/` in the feature worktree.

## Multi-relay manager independently accepted - 2026-11-07

Owner-authorized list/add/detail/enable/remove replaces the single-custom form.
Defaults are `wss://evolu.satflux.io` and `wss://free.evoluhq.com`; existing
custom-only preferences stay exact. Canonical nullable `evolu_relays` rows preserve
explicit empty/off selections, with strict bounded validation and account CSP.
Real SDK worker sockets supply per-relay state through origin-only observations;
Connected never asserts a complete backup. Same worker/SQLite identity is retained.

Independent security accepts19 TS/SFC +30 PHP cases; QA accepts3 SDK/Profile tests,
8 real Chrome worker/OPFS cases and10 responsive/keyboard UI cases. Six verified
review findings are closed, including stale relay revival, device-off resume,
missing-cache pre-auth exposure, queued status replies and SDK owner-query redaction.
Reviewed runtime30 `ebae93dc...18ac3c` / source54 `f9ca87d8...cd60a8` match before
and after independent checks. Main frontend2,129/201, PHP102/693 (relay/CSP/API +
guide selections), type110, lint48/52, locale/build, Pint/PHPStan/API/changelog pass.
Local AST documents87/87 changed complexity-qualified functions, including78 runtime
functions. No dependency, threshold, billing or external repository changes.

PR432 is open at prior published `da17233a`; main owns the scoped corrective
publication and exact-head verification. Merge/deployment remain separate pending
owner gates. Full contract, compatibility limits and private evidence are in
`EVOLU_RELAY_MANAGER.md`; unrelated primary work is preserved.

## PR432 second review accepted locally - 2026-11-06

All four findings are valid and corrected against published `ee294579`: reject
bare hexadecimal numeric hosts, require recovery backup before closing tabs,
cache successful public derivations by weak owner identity, and allow default-only
keyless profiles after explicit account-bound local owner confirmation. Retained
other-account owners, custom destinations, stale callbacks and device-off remain
guarded. The session proof contains only public account ID/key provenance.

Independent security/QA accept runtime20 `67ba4a4d...cdf5a9` / source36
`b93cdd7e...a0e814`, with all bytes verified before/after. Security56 TS +20 Profile
+23 PHP and QA26/3 pass, including actual SDK/SQLite and Profile race coverage.
Main frontend2,052/196, focused87/4, isolated PHP69/481, Pint/PHPStan, type110,
lint48/52, locales/build/API/changelog pass; local AST documents37/37 changed
complexity-qualified runtime functions. No external docstring success is inferred.

PR #432 remains open; main owns corrective publication and exact-head verification.
Prior-head CI passed; new-head checks need separate evidence. Merge/deployment are
pending owner gates. Full contracts/limits/evidence are in `EVOLU_CUSTOM_RELAY.md`
and private `reviews/evolu-custom-relay/second-review/`. Unrelated primary work,
old-worker restart guidance and existing test ratchets are preserved.

## PR432 review fixes independently accepted locally - 2026-11-06

PR #432 is open. These corrections were verified from published review base
`3b0d1e1c`; merge and deployment are separate pending gates. All five supplied inline findings and the cache-writer test nit
are verified still valid and corrected. Secure numeric-host validation rejects
trailing-dot aliases; Profile probing preserves compatible configured fallbacks.
Status records acknowledge the PR; five guides/notifications require loading
settings, closing all Satflux tabs and reopening.

New workers boot local-only and managed public owner registrations reconcile
canonical relay preferences across cooperative tabs. Atomic URL/mode/public-owner
association guards prevent cross-account or torn-cache destination routing;
unknown canonical identity pauses synchronization. No mnemonic/private key is
persisted or sent by this mechanism. Old/crashed worker references require the
close-all-tabs step; already in-flight protocol traffic has SDK100ms disposal grace.

Independent QA/security accept runtime19 `cbef0059...c8e22` and source34
`b878983d...754c84`. QA actual-source helper/real SDK+SQLite scenario1 and SDK5,
security12/1, author79/6 and main43/2 pass; selections overlap. Main full frontend
2,024/195, PHP64/458, scoped Pint/PHPStan, lint48/52, locale and build pass.
Final typecheck110 passes after two erased test-type assertions. The test's
emitted JavaScript is byte-identical; no baseline/runtime change is introduced.

Local AST audit documents all26 changed complexity-qualified named TS/Vue and
all3 PHP methods. This is local evidence, not a fresh external docstring result;
the prior CodeRabbit76.92% warning covers older source. No threshold is weakened,
CLI installed or manual bot command issued. Full findings, contracts, review
manifests and limitations are in `EVOLU_CUSTOM_RELAY.md` and private review evidence.
Main owns scoped source publication to the existing PR; no PR merge/deployment.

## PR432 master conflicts resolved and published - 2026-11-06

Integrate master `b4c2270d` (merged PoS PR431) into relay head `30a9363e`
without rewriting published history. Only PROJECT_STATE, ROADMAP and DECISION_LOG
conflict: retain both complete parent prefixes and their common history exactly
once. All30 reviewed relay source/test/doc bytes retain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`; all12 nonconflicting master
paths retain master bytes. The feature diff remains exactly35 paths.

Independent QA `/root/relay_conflict_qa` accepts this content-preservation proof.
Prior relay security acceptance remains applicable to unchanged relay bytes; this
is not a new semantic/security review of unrelated source. Main PHP182/1408,
type110, lint48/52, locale parity and production build pass on the combined tree.
Full frontend1,998/194 also passes. The inspected PHP target is network-disabled,
SQLite-memory with synthetic uncached configuration. No dependencies/ratchets change.

Conflict resolution is published as `3b0d1e1c` and PR #432 is open. Its remote
head and mergeability were verified separately; new-head CI requires its own
evidence. PR merge and deployment remain pending owner approval.

## Evolu relay source publication authorized - 2026-11-06

The owner requests pushing the independently accepted additional-relay feature.
Prepare one scoped commit on `feat/evolu-custom-relay` in the owned Satflux
repository, then verify the actual remote tip. Product bytes remain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`, accepted independently by QA
and security. Exactly35 paths include those30 reviewed source/test/doc files and
five coordination records; no unrelated primary edits or private fixtures belong
in the commit. Prior frontend1,940/192, PHP58/432 and static/build/API results
remain attributed to the reviewed bytes; no product checks are repeated for this
publication-only documentation update.

Source publication is complete and PR #432 is open. Merge and deployment remain
separate pending gates; fresh remote checks require actual-head verification.
The primary checkout remains on its existing branch with unrelated work preserved.

## Additional Evolu relay implemented and independently accepted - 2026-11-06

Owner-confirmed custom relay alongside the default is implemented locally on
`feat/evolu-custom-relay`, base local origin/master `2154101e`. Account persistence,
strict secure origins and one deduplicated transport list cover boot, AppOwner and
SharedOwners. New custom settings offer both; legacy custom-only/device-off and
keys are preserved. UI and user docs cover all five locales.

Independent QA/security accept runtime16 `5bbc13eb...dc39d7` / source30
`4594a9d3...beb9f`. The intermediate HIGH logout-cache privacy regression is
removed and independently reproduced before/fixed after. Main full frontend1,940
/192, type111, lint48/52, locale/build, PHP58/432, API export/three-schema validation
and changelog11 pass. Author PHP67/262, QA extra16 + focused71/7 + PHP47/202 and
real SDK protocol6 pass; selections overlap. Five unrelated offline email-DNS
cases remain excluded from the earlier broad-PHP claim. Scoped Pint/PHPStan pass.

Retain `storage/app/private/worktrees/evolu-custom-relay`; full contracts, reviewed
hashes, validation limits and private evidence are recorded in its feature note
`docs/development/EVOLU_CUSTOM_RELAY.md`. Primary edits are preserved. This local acceptance predates source publication;
PR #432 is now open. Merge and deployment remain pending, with migration/assets
compatibility required before rollout. Production state remains unverified.
No other repository or billing scope is added.

## PR431 seven review findings corrected and independently accepted - 2026-11-06

All seven supplied findings reproduce on published38e03295 and are corrected.
Raw detail completeness and Unicode-aware product titles are validated before
cached overlay; only genuine list paths opt out of full-detail confirmation.
Both aliases reject invalid input with422 before normal or legacy merging/API
work. Valid legacy aliases retain JSON template transport and explicit clears.
App-list writes honor the caller guard; failed reads retain existing Delete
without Save. Original category arrays and post-submit same-app drafts survive.
The legacy bypass and category-ID reuse regression found independently are closed.

Independent QA accepts JS66/3, PHP135/1014 (including its extra legacy proof),
and Chrome10/10. Security accepts the same frozen runtime7 `fe560b24...79bec`
and source11 `1394cad4...50f02`; no unresolved finding remains. Main full
frontend1,969/193, PHP218/1407, lint48/52, type110, five-locale parity, build,
scoped Pint/PHPStan and AST update pass. Earlier superseded frontend evidence
is excluded from these final-head claims. No dependency or ratchet changes.

Publication uses the existing owned PoS branch; verify remote head and new CI
before claiming external success. Resolve only the seven verified review threads.
No PR merge, deployment, production state or unrelated primary edits are changed.

## PR431 base conflicts resolved and independently accepted - 2026-11-06

Integrate master `2154101e` (merged landing PR430) into published PoS head
`5a105124` without rewriting its history. AppsShow retains the exact master
template and published PoS script plus AppScrollPane import. Both prefixes in
PROJECT_STATE, ROADMAP and DECISION_LOG are preserved with common history once.
Other five PoS runtime files and all88 auto-merged master paths retain parent
bytes; the combined PR still changes exactly13 scoped paths versus master.

Independent QA accepts focused71/3 and Chrome18/18, including desktop/tablet/mobile
scroll/footer geometry. Independent security accepts the exact merge and focused45.
Main full frontend1,948/192, PHP127/784, lint48/52, type110, locale parity,
production build and AST update pass. Runtime6 `4a40f576...5cf9fb` / source9
`42586cbb...4252e6` match the reviewed merge. PHP source is unchanged; earlier
Pint/PHPStan evidence carries forward. No new dependency or ratchet changes.

Publish the resolved feature branch under the owner's conflict-fix request, then
verify the actual remote head, PR mergeability and new-head CI separately. No PR
merge or deployment is authorized or performed; preserve unrelated primary work.

## PoS settings preservation accepted before publication - 2026-11-06

The owner conditions source publication on preserving the other settings. A pinned
BTCPay v2.4.7 audit finds missing formId/customTipPercentages request fields and
untouched empty/null values replaced by UI defaults. Both are corrected. All20
writable PoS base fields are covered; metadata remains excluded. Confirmed save
snapshots advance from immutable submitted values, preserve later edits and reject
obsolete app/detail responses. Explicit edits, clears and normalized flags remain.

Independent QA accepts Chrome15/15 with actual PHP request building and PHP43/386.
Independent security accepts focused37/37 and boundary8/8, with no unresolved
finding. Runtime6 `1721c2cb...477cd7` and source9 `60687a9e...aefaa3` match both
reviews. Main PHP102/560, full frontend1,891/186, lint48/52, type110, locale,
production build and AST update pass. Contract, normalization and full review
evidence is recorded in `POS_EDITOR_ROUNDTRIP.md`.

Tax3 fields have cache/payload preservation evidence, not official host support.
Legacy customer-data selector compatibility remains separate; existing formId is
preserved. Production state/version is unqueried. Publish only the owned PoS fix
branch with the verified final gates; no merge/deployment is authorized. Preserve primary edits.

## PoS editor product and tipping hydration accepted locally - 2026-11-06

Branch `fix/pos-editor-roundtrip` is based on owned master `e584490d`; the affected
source matches the owner-reported deployed candidate `76422c07`, without inspecting
production. AppsShow passed list metadata as complete editor props, suppressing the
detail fetch. It now retains the selected detail separately, rejects stale route
responses and requires complete settings before editing. PoS cache/update overlays
preserve omitted fields, false/empty values, product aliases and canonical tip text;
failed or malformed reads block unsafe saves. Customer-data request/formId remains
a separate pre-existing contract audit.

Independent QA/security accept identical runtime6 `db71578a...710df5` and source9
`57e944e5...7165e9`, without unresolved findings. Real Chrome10 scenarios plus a
stable-ID roundtrip, focused frontend25, full frontend1,879/186, PHP96/320, targeted
Pint/PHPStan, lint48/52, type110, locale parity, production build and AST update pass. Full evidence
is recorded in `POS_EDITOR_ROUNDTRIP.md`. All tests use synthetic
isolated state; production host/version and merchant state are not validated or
changed. No source publication, merge or deployment has occurred for this fix.

The correction is ready for owner-directed publication; retain
separate merge/deployment approval. Preserve unrelated primary checkout edits.

## PR430 review remediation accepted locally - 2026-11-06

Verified all supplied findings against published `665cc2f6`. Critical auth/pricing
requests now start independently of idle and gate page outcomes on confirmed
results. A narrow auth outcome helper preserves the existing boolean API and
all original generation/CSRF/key/tenant handling. Native header fragments select
the PoS panel without activating its iframe; a coalesced, cleaned-up task makes
the final scroll respect reduced motion after all native listeners.

Missing/error company views retain their footer. Public version requests share
successful/in-flight state with failure retry. WooCommerce points to verified
setup/compatibility documentation; primary anchors meet AA and public CSS includes
FreeProNotice borders. Operator docs explicitly forbid secrets in the entire
public demo URL. Missing handler/helper/test-method documentation is supplied.

Independent QA/security accept runtime15 `f936a8ae...68369f` and source/tests22
`c5c36215...d1bed`. Local full frontend1,910/191 passes before the final native-event
timing correction; final focused53/4 and unchanged footer16/3 pass afterward.
PHP44/279, final type111 known/zero new, lint48/52, locale parity, build, Pint and
AST-only graph pass. QA native16/16, focused15/15 and extra5/5 pass. No unresolved
implementation finding remains. Main owns the next scoped corrective commit/push
and actual new-head CI/review verification; prior CI42126779244 green remains
attributed only to `665cc2f6`. No merge or deployment is authorized.

## Landing showcase source publication authorized - 2026-11-06

The Product Owner requests pushing the completed `feat/landing-showcase` work.
The publication set is exactly 82 paths: reviewed runtime70, source/tests77 and
five coordination documents. Runtime `8466ca0e...ab90f3f` and source/tests
`fbb90756...4493576` match the frozen QA/security review evidence. Publication additionally removes
two trailing spaces from one blank CSS line; the implementation contract records
publication hashes and unchanged CSS semantics.
The primary checkout and original design artifacts remain preserved.

Local evidence remains frontend1,882/188, PHP44/279, lint48/52, typecheck111 known
and zero new, locale parity, production build, Pint/PHPStan and AST-only Graphify.
Only publication documentation and the verified CSS blank-line normalization follow
acceptance, so product tests are not repeated. Main commits/pushes the bounded set and verifies the actual remote head.
Remote CI/review and deployment are distinct evidence; no merge or deployment is
authorized by this source-push request.

## Landing showcase implementation and navigation follow-ups - 2026-11-05

The owner authorizes implementing the approved V6 homepage and desktop footer
placement. Work is isolated on `feat/landing-showcase`, based on freshly fetched
`origin/master` `e584490d` (expense reconciliation PR429 merged in source).
The unrelated primary checkout remains preserved. No deployment is inferred.

A runtime Laravel configuration selects `legacy` or `showcase`, defaults safely
to legacy and supplies the same escaped enum to both SPA documents. Both Vue
routers share a document-stable selector. The showcase uses a matching localized
Blade first-paint hero, genuine synthetic screenshots, existing production
headers, live pricing/promotion policy and accessible tool/solution/image dialogs.
Desktop footers move inside audited primary scroll hosts; nested/embedded panes
do not create additional footers. Optional HTTPS PoS demo loading requires a click.

Independent QA/security accept runtime70 `8466ca0e...ab90f3f` and source/tests77
`fbb90756...4493576`. Final frontend1,882/188, PHP44/279 assertions, config-cache
switch/rollback, locale parity, lint48/52, typecheck111 known/zero new, build,
Pint/PHPStan and AST-only Graphify pass. The credentialed-demo disclosure and two
nested-footer branches found during review are fixed and independently retested.
The preserved header still overflows at768px and has existing drawer accessibility
gaps; these are follow-ups. Source is local/uncommitted, not published. See
[implementation contract](LANDING_SHOWCASE_IMPLEMENTATION.md) for exact ownership,
acceptance, switch/rollback and separate Inertia/SEO/quality recommendations.
Source publication, merge and deployment remain separate actions.

## PR429 second review fixes accepted locally - 2026-11-05

All five CI jobs pass on published `714e078b`; CodeRabbit exact-head review has
92.41% docstring coverage. Its three new inline and one outside-diff findings are
now fixed from that baseline. Payment mapping and expense/accountant projection
use scoped snapshot indexes with duplicate/wildcard safety and status filtering
before the fifty-row bound. Node engines are `^22.13.0 || >=24.0.0` with unchanged
dependencies. Server unmark/cancel lock fresh Company → Expense state and require
explicit unlinking; bulk cancellation skips only the exact linked-payment code
and returns truthful counters. Other failures propagate. Existing localization
now consumes the actual HTTP rejection envelope.

Independent QA/security accept runtime45
`2fcb0006f3c4cd7dd9da2909e1a668c9b55534c64a16583243790bd925c73a4e`
and source/tests/CI72
`dbbcab19e27396f7ce1f72ca3f51a289072fab6d23ad16b2ec354286863b81c7`.
Main full frontend: 1,854 tests / 185 files; typecheck111, lint48/52, production
build and scoped helper lint pass. Backend: 170 focused / 867 assertions,
Pint/PHPStan pass; independent status/bulk15 / 126, helper13 and index/canonical31
pass (overlapping selections). QA separately verifies actual HTTP-to-UI guidance,
linear access budgets, scope/parity and both bulk ordering/repeat paths. The
PostgreSQL four-case gate is unchanged; no new PostgreSQL execution is claimed.
AST-only Graphify update completes. No unresolved QA/security blocker remains.

Next authorized step: main commits/pushes only the bounded second-review fixes to
the same branch, then verifies fresh actual CI/review. Prior green evidence stays
pinned to `714e078b`. Preserve dirty primary work; no merge/deployment is authorized.

## PR429 review remediation accepted locally - 2026-11-05

The owner requests correction of PR429's failed PHP job and CodeRabbit findings.
Actual run `41996402233` on `1cb68a96` passed PHP tests; only Composer audit failed.
Six affected Symfony packages now use 7.4.23 and the locked audit is clean. All nine
inline findings, the follow-up and four test notes are implemented. The original
55.26% docstring warning is addressed through meaningful documentation, retaining
the 80% threshold; fresh remote coverage is still required.

New manual/automatic/create bank associations use reciprocal encrypted claims and
one canonical reactive projection. Revision-checked conflict recovery, linked-edit
guards and released receipts preserve independent payments. Historical claimless
rows and mixed older clients remain explicit limits. A native restore review also
fixes rejection of system owner metadata while preserving live trusted routing.
Independent QA/security accept frozen logic runtime44 `9e494a62...bddf2f` and
source/tests/CI68 `5edbc1e1...e8c7ec`. Publication changes only proved docstrings:
runtime44 `b89dbb70...366899`, source/tests/CI68 `4d52bf9c...1e0013`.

Full PHP with guarded synthetic configuration and DNS: 2,902 tests / 13,860
assertions, zero failures, 58 conditional skips and one existing deprecation.
Actual PostgreSQL: 4 / 74, zero skips; the CI gate now requires four. Frontend:
1,830 / 183 files; typecheck111, lint48/52, locales, build, API, Pint1122 and
PHPStan pass. Scoped post-documentation Pint/ESLint pass. AST-only Graphify update
completes. No ratchet/audit/authentication rule was weakened.

Staging also removes exactly one redundant EOF LF from the new claim helper;
AST/emitted JavaScript remain identical.

Main's next authorized step is scoped commit/push to the existing owned
`feat/expense-reconciliation` branch, then actual new-head CI/review verification.
Preserve unrelated primary checkout work; no merge/deployment is authorized.
See the feature branch's `docs/development/PR429_REVIEW.md` for exact evidence.

## Expense reconciliation source push authorized - 2026-11-05

The owner explicitly requests commit/push of the reviewed 51-path feature to owned
`webiumsk/satflux:feat/expense-reconciliation`, based on `cb55aab0`. Fresh byte
verification matches the accepted implementation. Staging exposed one extra EOF
blank line in `bankPaymentValues.ts`; removing exactly one LF leaves executable
content unchanged. Publication runtime23 is `620c7f00...956f2` and source/test/CI35
`c947959b...8557b`; original review hashes remain in the implementation note. Initial
remote branch and PR checks return none. Main owns commit/push and exact remote
tip/tree verification; unrelated primary work is excluded. No merge/deployment
authorization or new test/CI success is inferred.

## Expense payment review and duplicate warnings implemented - 2026-11-05

The owner authorized the first two recommended improvements. The isolated
`feat/expense-reconciliation` worktree starts from freshly fetched `cb55aab0`
(merged PR427/428), preserving unrelated primary checkout work. Explicit outgoing
payment-to-existing-expense confirmation and advisory create/edit/import duplicate
warnings support local-first and legacy server invoicing, with five-locale UI/docs.
No supplier/contact schema or QR/relay implementation is included.

Independent QA and security accept final runtime23 manifest
`982868ca...5ef95` and source/test/CI35 `edf41386...46850`.
Post-handler evidence verification, transaction-first locks and recorded-direction
action gating resolve the reproduced review findings. Real PostgreSQL control
reproduces historical `40P01` and the corrected clean conflict; CI now requires two
actual contention cases without skips. Final frontend is 1,760 tests / 177 files;
129 focused SQLite / 563 assertions and independent PostgreSQL 2 / 36 pass.
Lint48/limit52, unchanged typecheck ratchet111, locale parity, build, API, Pint and
PHPStan pass. Full PHP has six DNS-dependent failures also reproduced on unchanged
base, plus 54 conditional skips; do not claim global or remote CI green.

See the feature worktree's `docs/development/EXPENSE_RECONCILIATION.md` for exact
hashes, commands, reviews and limits. Work remains local and uncommitted; no PR,
publication, merge, deployment, real payment or merchant-data access. The next step
is owner review and applicable source-publication/CI/merge/deployment approval.


## PR428 semantic review clean; supported test-helper documentation repair - 2026-11-04

The owner-authorized `@coderabbitai review` comment `6638746873` was posted after
the allowance reset at 18:30:30 UTC. Bot reply `6638749080` confirms acceptance;
check `126777122714` completes successfully at 18:33:23 UTC on `1e68623a`.
The actual walkthrough reports no actionable comments, exact-head reviewed coverage
and zero review threads. The earlier held-upload test finding is addressed.

One pre-merge warning remains: 0% documentation at the unchanged 80% threshold.
The first five Vue JSDoc additions did not cover the measured supported helper.
Author and independent QA inventories agree that `render()` in
`resources/js/__tests__/expenseFormDefaults.test.ts` is the only changed named
function in the three supported TypeScript test files. That matches the reported
one analyzed function; CodeRabbit does not explicitly identify its symbol, so the
attribution is a diff-based inference. A four-line JSDoc describes persistence/route
mode, the optional initial-copy marker and awaited hydration. No executable changes.
Independent QA accepts exact test hash
`8fba8d02cb3143faf9a3e39e49d6a74fed254a590ab54c47d0d2f7ae72548bf7`:
JSDoc attaches to `render()`, executable tokens, normalized AST, stripped TypeScript
and emitted JavaScript are identical to `1e68623a`. Scoped lint and whitespace pass.
The six-file source/test manifest is
`88d4515e64d9f9022a85d5bb8603c3e8e62bd23ebe62db77aed5ccb882b0f3ab`;
the three production files retain accepted manifest
`2842ec51ffb268f5e6e7b0fddc9add01ae07ae23e72e20cb088f332ce6351411`.
Publish this comment and three state documents, then verify fresh exact-head CI and
pre-merge results. Official CodeRabbit docs support the separate command
`@coderabbitai run pre-merge checks` to refresh configured checks in the existing
walkthrough: https://docs.coderabbit.ai/pr-reviews/pre-merge-checks#run-all-checks .
Its hourly allowance cost and cache guarantees are not documented; do not assume
either or hide the warning. All five prior CI checks remain green. No merge,
deployment, production access or configuration change.

## PR428 initial CI green; reviewed test and documentation follow-up ready - 2026-11-04

All five required checks completed successfully for `cf064146` in CI run
`41877739679`, attempt 1. The final verification at 17:53 UTC reconciles the
workflow and each exact-head check, including PostgreSQL's 2 tests / 108 assertions
and zero-skip gate. Earlier API snapshots reported inconsistent job states; they
are not evidence of actual queue duration or a failed test. No rerun or cancellation.

CodeRabbit review `5772913881` on that commit completed at 17:36:07 UTC with no
functional or inline finding, one held-upload test nit (`56789731b8f39c6664dd3162`),
and a docstring coverage warning of 0% against the unchanged 80% threshold.
Five meaningful JSDoc contracts and deferred-upload assertions in the existing
local/server test address both. Independent QA and security confirm that production
Vue tokens, normalized ASTs, emitted JavaScript, templates and styles are unchanged.

Both reviewers accept the four-file follow-up manifest
`afaafe54bc00b86cde400c4d34e940cbf2776b148fa4d962b5f41d22e5ad724d`.
The complete fix has runtime manifest
`2842ec51ffb268f5e6e7b0fddc9add01ae07ae23e72e20cb088f332ce6351411`
and six-file source/test manifest
`1e691c39af27e57e2ae3d3f5d7cdc70ef25f2e75bb2d793d631c5d23889ec244`.
Author validation passes 101 tests in three files, lint and the existing typecheck
ratchet. QA verifies both held-upload cases pass and an isolated mutant removing
only the upload awaits fails both. Publish these four files and three state documents,
then require fresh CI and actual CodeRabbit coverage of the resulting head. Invoke
`@coderabbitai review` after the allowance resets if automatic review is limited.
No threshold, authorization, financial, backend or CI configuration change. No merge,
deployment or production access is authorized by this milestone.

## Duplicate expense defaults repair independently accepted - 2026-11-04

Owner clarified that new expenses work and duplicated expenses fail. GitHub verifies
PR427 merged as80139525 at16:56:28UTC; Live deployment is owner-reported. On this
master baseline, QA reproduces four duplicated-edit failures while new-form and
ordinary edit controls pass. All four duplicate actions persisted a copy then
opened ordinary edit, where isNew-only guards suppressed VS/delivery defaults.

The repair propagates a clone-ID presentation marker through list/detail actions
in both modes, captures it only on the matching initial edit context and permanently
revokes it after company/expense/route changes. Copied values are not rewritten on
hydration; source input follows until manual/cleared/imported target overrides.
Copies still update their existing ID, with no create/mark-paid/status or authority
change. Successful copy save awaits attachments and replaces the edit history entry;
normal saves keep push. Failures preserve recovery. No backend/schema/financial
writers or general source watchers changed.

Both independent reviewers accept runtime3 manifest
`9125a87c8ed6c188ee0890ba07c554cfb164ddba9a9421ce8eea1cf8c8641e10`
and source/tests6 manifest
`f7768cfffcecc03b1df8c2845cb89fa35b81a4829a28bf2de5c03a60dea1a679`.
Author and each reviewer pass101 repository cases/three suites; QA and security
also each pass28 actual mounted DOM probes. Scopes overlap, not aggregate counts.
Main full frontend1700/173 passes; lint0errors/48knownwarnings, unchanged typecheck
baseline/no-new, EN/SK/ES/DE/CS parity and production build93seconds pass.

Publish only the six accepted source/test paths and three state documents from
fix/expense-form-default-events; verify staged/remote tree and current head, then
complete fresh CI and actual CodeRabbit review. Main checkout/unrelated work remain
preserved. Graphify unavailable, not installed/rebuilt. No new merge/deployment or
production replay is authorized by this follow-up.

## PR427 final two test-coverage nits repaired - 2026-11-04

Actual review 5766899006 completed 10:27:45 UTC on green 23b6f927. There are no
open inline threads (all 32 resolved); five pre-merge checks pass with 89.81%
docstring coverage. Two test nitpicks remain for the next reviewed publication:
PostgreSQL must return a genuine movement after the snapshot prefix, and the
client must continue after an empty nonterminal page.

Both test-only repairs are frozen in manifest
`31757baa76148de3a8c6b9ba85111c861c08264d08e3495ffbbaedfb5d1bb7e7`.
The PG test follows real encrypted next_cursor values from its original head to
movement 42992 and checks identity/reference/company/source/amount/currency/no
internal flag, preserving the two test methods and all existing plan assertions.
The client test pauses strict empty-page ACK, proves no save or next read before
admission, then checks cursor forwarding, movement import and final persistence.
Author and independent QA each pass all 122 affected client cases; scoped lint
passes. Independent security accepts the exact two-test +47-line diff with no
weakened guards; its R13 runtime acceptance remains applicable. Author actual PG16.11/PHP8.3.31 tests pass two / 108 assertions, with Pint
and owned-schema/container cleanup. QA reviews the PG artifact without executing
PG. Production runtime, migration, CI and financial source remain unchanged from
the independently accepted R13 runtime manifest. Root publication scope is exactly
two tests plus four development documents; no new application behavior.

Publish through existing GitHub connector with verified staged-tree equality,
complete all five fresh CI jobs and actual manual review after allowance (last
10:13:59 UTC; conservative next 11:15 UTC). No merge/deployment/production action.

## PR427 bounded-candidate and PostgreSQL CI repair accepted - 2026-11-04

Both independent reviewers accept runtime-four manifest
`2c1fb965a9f9c2dc2c052c17ffdbe8137467fa1dd3675436e96058b1d29936bc`
and source/tests-six manifest
`e8ebc2c99dac7c009f3af483e913f90b65e3729f08c979240ce8f1464e739ffa`.
Exactly ten paths are publication scope. Actual review 5766156195's inline,
follow-up and test nitpick are repaired: bounded raw candidates with exact SQL
snapshot projection, concurrent PG index migration and a fifth PG CI job.

Backend author 43 HTTP/SQLite tests / 312 assertions and 110 related / 528 passed
(overlap), Pint on four files passed and PHPStan has no new finding beyond the
inherited local Scramble gap. Devops executed actual PG16.11/PHP8.3.31 tests:
two / 100 assertions passed, including real Migrator concurrent lifecycle,
INVALID leftovers/catalog recovery, plans and cleanup; new-test Pint, YAML and
positive/negative JUnit gates pass. Security independently executed 43 / 312 in
SQLite and both actual PG cases / 100 assertions on the exact frozen bytes;
owned disposable schemas/container/tmpfs were removed. QA passed 11 coverage,
31 retained Vue progress, four actual Evolu/SQLite empty-page probes and native
SQL budget/parity/traversal checks; it reviewed PG evidence without executing PG.
All JavaScript and financial/owner/checkpoint/credential code remains unchanged.

Verify committed bytes/remote SHA and complete all five fresh CI jobs, including
PostgreSQL (bank sync plans), then request actual manual CodeRabbit after allowance
(last request 09:12:09 UTC; conservative next 10:13 UTC). Inspect all findings and
continue until green. No production DDL, replay, merge/deployment or unrelated work.

## PR427 scan-progress and index repair independently accepted - 2026-11-04

Both reviewers accept runtime-four manifest
`0090c75404f8564b373b10dc30f5b7b475b1e5ff7fc69c619988202c73032557`
and source/tests-six manifest
`d075ba2ee6bcff1b82cc4cd1f84bf61a63fe4e57b8a3226fa27a5a6bfa89c589`.
The two findings from actual review 5765381601 are repaired: protocol 2 advances
scan positions while retaining unresolved coverage, and partitioned reads use a
reversible composite index. Unknown, unverified owner admission, strict coverage,
IndexedDB storage and credential ordering retain their existing boundaries.

Main full frontend: 1,675 tests / 173 files, build 69 seconds, lint zero errors /
48 known warnings passed. Author 147 / six focused suites and typecheck 111
existing / none new passed. Backend 41 HTTP/SQLite tests / 264 assertions and
Pint passed; local PHPStan has no new findings beyond the inherited Scramble gap.
Independent QA passed 262 repository cases / nine suites, 31 progress probes,
nine retained Evolu/SQLite and 14 credential-order probes. Security passed 221 /
six suites, 31 progress probes and 41 PHP tests / 264 assertions. Scopes overlap.
Both reviewed the author's 60k-row SQLite/PostgreSQL 16.11 index plans; QA also
executed equivalent SQLite ordering/rollback probes. No independent PostgreSQL
execution is claimed. Standard index construction requires deployment planning.

Publish exactly ten accepted paths, verify committed blobs and remote SHA, then
complete all fresh CI and another actual manual CodeRabbit review after quota.
Last accepted request was 08:10:15 UTC; do not duplicate an active review. Backend
must deploy first or with the protocol 2 client; ordinary pagination remains
compatible. No production DDL, replay, merge/deployment or unrelated publication.

## PR427 progressive history repair independently accepted - 2026-11-04

The repair addresses all three inline and one supplemental finding from actual
review5764391436 on b78d09b8. Optional authenticated encrypted cursors alternate
bounded head overlap and cyclic nullable-created-at/UUID keyset backfill. Strict
page coverage and fresh expected-owner admission precede advancement; unproven
fallback writes nothing. At most two bounded read chunks precede unraced financial
work and transaction-complete ciphertext-only IndexedDB CAS. Ordinary listing,
authorization and legacy import contracts remain. Unknown never advances its chunk;
admitted incomplete scheduling may overlap covered rows without re-auto-matching.

Both independent reviewers accept runtime13 manifest
`d4b6bcc44553b8ca43511519bc1a62ca327aca10aaea018d7517f7df228ab51a`
and source/tests21 manifest
`6c628d695391f39cd72a4bbf4ed4b6cb1674e919d14b55f5d718860bf2c232bf`.
Main full frontend1666/173, build70seconds, lint48known/zeroerrors, type111existing/
no-new and five-language parity pass. Backend31HTTP/SQLite tests/153assertions and
Pint pass; no new PHPStan finding beyond the unchanged local optional Scramble gap.
Security222repository/6suites +20Vue +9Evolu/SQLite +2owner probes pass; QA337repository/13suites +20progress +33Evolu/SQLite
(9new/24retained) +14credential-order probes pass. Scopes overlap, not aggregate totals.
Native IndexedDB four-case Playwright source is reviewed/listed, not locally run;
fresh CI execution is mandatory because sandbox browser syscalls are unavailable.

Publish only25 accepted paths and verify committed bytes/remote SHA, then require
all fresh CI and another actual clean manual CodeRabbit review. Backend must deploy
before or with this client; old clients retain ordinary endpoint behavior. No
universal history-arrival/latency guarantee, production replay, merge/deployment,
migration or owner/key/billing change. Graphify graph remains unavailable.

## PR427 credential-order repair independently accepted - 2026-11-04

889e1e69 CI41781754322 is green and all26 prior threads resolved. Independent
security then confirms Medium SEC-WISE-CONNECT-ORDER: already-entered old A POST
can overwrite newer A credentials after A-B-A resets page busy state. Actual page
and actual encrypted SQLite/fake-HTTP service fixtures reproduce it, without real
credentials or production writes. Earlier accepted entered-outcome guarantees did
not cover ordering; the busy reset was introduced3ac059d1.

Main accepts only a shared runtime per-server-bridge POST gate across hook instances
and both connection modes. Queued intent revalidates immediately before dispatch;
entered outcomes remain awaited with exact errors, no cancellation/race/retry or
compensation. Independent bridges remain concurrent and status reads stay outside
the queue. Ambiguous transport/cross-client global order is not proven or changed.
Frontend owns composable/new queue/inbound/server/queue tests; main owns docs/Git.
Both reviewers accept runtime2 4304ae73...244a9/source-test5 3734a970...11f44. Main
final1623/171, build75seconds, lint48known/zeroerrors/type111existing-no-new/locales
pass. Author151/7, QA219/9+14new+47retained and security119/3+14+7 pass with overlapping
synthetic scopes. All five blobs match; exactly nine paths form publication scope.
Verify committed bytes/remote SHA, fresh full CI and actual manual review before
all-green completion. No merge/deployment or production action.

## PR427 ninth repair independently accepted - 2026-11-04

Published3ac059d1 has green CI41777047822. Actual manual review5763674984 reports
two inline and four supplemental findings despite five passing pre-merge checks
and88.52% documentation. Open threads PRRT_kwDOQ75Xa86zdbx8/PRRT_kwDOQ75Xa86zdbyJ
cover initial-metadata A-B-A credential dispatch and same-company routing refresh.
Supplemental items cover Wise importer counts, status retry state, bounded reads
and failed-sync error reporting. Independent baseline probes reproduce the issues.

Frontend owns bounded read helper/composable/only optional Wise-status API controls
and tests; main owns page/test/docs. Current-page refresh now runs once on every
semantic routing change, with batches only on company change, revising the prior
address/status-only routine choice. Read deadlines cover metadata/bridge/status
GET only, preserving entered credential/sync POST and financial outcomes. Default
nonbank callers, trusted registry and owner/Unknown policies remain. Both reviewers
accept runtime5 9d0e0c91...68b7e/source-test10 67dabf48...1d9d0b. Main final1609/170,
build76seconds, lint48known/zeroerrors/type111existing-no-new/locales pass. Author151/8,
QA205/8+22new+25retained and security179/5+22+25 pass with overlapping synthetic
scopes. All10bytes match; exactly14 paths are publication scope. Verify committed
blobs/remote SHA, full fresh CI and actual manual review after capacity. No
production replay, merge or deployment is included.

## PR427 eighth review repair independently accepted - 2026-11-04

Publishedc0921567 has complete green CI41771525497, independently verified by QA.
Manual command6622425495 was accepted6622426859; actual review5763268988 at04:34UTC
reports three inline and two supplemental findings. Pre-merge checks pass87.72%,
but metadata recovery, generation dedup, Wise identity freshness, lookup recovery
presentation and recorded shared bridge routing require this follow-up.

Frontend owns the bounded composable/helper/registry/bootstrap and related tests;
main owns page/locales/docs/Git. Initial real-Vue probes reproduce six baseline
failures; targeted draft repairs pass with already-started financial controls.
The page uses the same bank-only recorded-route option and a semantic context
guard for displayed addresses. Default issuance helpers and owner policy stay.

Verified mapping authority is the existing AppOwner/secret-checked registry, not
the raw unfiltered companyShare query. Pinned routes require exact local owner
metadata, private fallback requires AppOwner. Readiness must include an installed
subscription before maintenance; the initial wait belongs to the existing bank
read budget. Both independent reviewers now accept runtime10 b1f3de86...a26c
and source/test15 e08706b2...9052. Main final1579frontend/169files, build73seconds,
lint48known/zeroerrors, changed-filelint0/type111existing-no-new/locales pass.
Author128/7, QA175/7+25actualVue and security168/7unchangedauthority plus76final
inbound+25probes pass with overlapping scopes and synthetic adapters. All15bytes
match. Known unresolved Wise connect rejects beforePOST; entered remote/financial
outcomes remain awaited. Captured context guards both page publication and new
post-financial reads, preventing old work importing another company's email rows.
Publish only19 reviewed paths, verify blobs/remote SHA, complete fresh CI and
actual manual review until green. Earlier1576 main run predates the final guards.
No merge/deployment, production replay or unrelated primary work is included.

## PR427 lifecycle regression independently accepted - 2026-11-04

Before requesting the next review, source audit plus an actual mounted Vue probe
confirmed a remaining before-import gap on7e9af5f1: disposing the bank view freezes
its query snapshot. A later Settings edit of the same UUID and late lookup/page
response could enter import with stale identity. The baseline reproduction fails;
this is distinct from the documented edits after an import already queues.

The two-path fix adds disposed/generation checks and cancels only active read
stages, including metadata/transport promises ignoring abort. Registrations and
timers are removed before financial entry; already-started import success and
identical Unknown still propagate. API/allocator/financial/backend files unchanged.

Both reviewers accept runtime1
`29becc3a71d698c9e04e90b8ca8e62b80390d48404b875014615e83d3c70cce6`
and source/test2
`d579efc011de5360d1aa3fdbc69e78f03c0afa6278ac7f1fa9b8fbf55e5dbc43`.
Main209cases/10bank suites, final build/lint48known/type111existing-no-new pass.
Author71/4, independent QA46repository+7actualVue and security46+7 pass with
synthetic storage/transport/import entry points; no financial writes in probes.
Parent7e9 had full1504frontend/167 and complete green CI41769392442. The new head
will run the entire CI; do not attribute parent full results to these new bytes.
Publish only6 reviewed paths, verify hashes/remote SHA and all fresh CI, then the
next authorized actual review. No merge/deployment/production replay.

## PR427 sixth review fixes independently accepted - 2026-11-04

The completed7cd2b9ec manual review reports one stale-identity inline finding,
one whole-fetch bound follow-up and one missing deleted-query regression.
All five pre-merge checks pass, including87.27% docstrings; these actionable
items keep the owner's all-green loop active. Published7cd CI41758379808 is green.

The working-tree repair adds normalized identity/generation freshness checks and
one20-second budget for metadata readiness, scoped bridge HTTP and pagination.
Caller-only request options recompute remaining time/cancellation while ordinary
allocator/Wise callers retain existing payloads/defaults. Timer cleanup precedes
financial import; partial/late data is discarded. Unknown metadata POST outcomes
are never compensated. A production-query deleted/live receipt test is added.

Both independent reviewers accept runtime4
`927a9d92058991f54782d535d855572bd77075ebec363227def28ac8eb2427a1`
and source/test7
`a63822f8354ac43c888f31a12252d789b88c0a78b0fca526ea35cb7def9f2406`.
Author78/6, QA94/10 plus13probes/2, security62 plus final2 recheck/13probes pass,
with overlapping scopes and synthetic adapters. Type111existing/no-new, scoped
lint0/API/default-caller checks pass. Main final fullfrontend1504/167, final production build and full lint48known/zero
errors pass on the frozen bytes; five-language parity remains unchanged. The earlier1503/167 main run began before the last
narrow error-path/test refinement; do not attribute it to the final frozen bytes.

No company CRUD lock or commit-wide identity atomicity is introduced: the guard
checks immediately before import, preserving the documented queued-edit boundary.
Publish only reviewed11 paths, verify new CI/capacity, and repeat the authorized
manual command until actual review green. No merge/deployment/production replay.

## PR427 fifth review fixes independently accepted - 2026-11-04

The five actionable findings from the completed manual review of3dce0b4f are
repaired on the same branch: stale/superseded server requests, bounded bridge
pages, visible local import errors and precise fresh-local ACK recovery.
No server, auth/CSRF, owner, key, financial authority or configuration change.

Both independent reviewers accept runtime4
`b7887cd1c5469280eda1ed1f6c5906ace1cfb770cff231d21bc584dd9417983f`
and source/test9
`b33cfdeb57808d06f013e4ffa552a55b99934bd1fe7dac4ea2af2999c19de663`.
Main fullfrontend1485/165 passes, focused55 and author46 pass. Full lint48known,
type111existing/no-new, build/locales pass with unchanged ratchets. QA112repository
and14temporary pass; security101repository/14temporary and unchanged backend
ACK contract6PHP/64assertions pass. Scopes overlap and transport/native adapters
remain synthetic. No unresolved reviewer finding remains on these exact bytes.

Publish only the reviewed13-path delta, verify committed manifests and remote SHA,
resolve the five addressed threads and wait for complete exact-head CI. Read the
actual capacity-reset notice before the next authorized manual review command.
The previous review's docstring86.92% passes but does not establish the new result.
No merge, deployment, production replay or unrelated primary work is included.

## PR427 documentation warning and manual review loop - 2026-11-04

Owner explicitly asks to wait for every test, issue `@coderabbitai review` after
capacity returns, repair findings and repeat until green. CI41751685173 passes
all four jobs on published6ba7e908; CodeRabbit skipped review due to capacity,
next available approximately01:30UTC. Its retained docstring warning is addressed
with genuine contracts only: frontend70, API3 and PHP17 prose docblocks.
No executable, annotation, test logic, threshold or configuration change.

Comment/metadata20 manifest: 75cab067...06b7. PHP tokens across all12 PR PHP paths
remain identical to6ba; Pint5/syntax pass, API453 routes stay unchanged and parser
fingerprint is refreshed. Independent QA accepts every final20 byte:14TS parser-derived tokens and emitted
JS plus5PHP executable tokens match6ba exactly. Lint48known/type111existing-no-new,
Pint5/API pass. Main-owned scoped publication and fresh CI precede manual review. Then post the exact authorized
command after reset and examine actual review output, including pre-merge checks.
No merge, deployment, production replay or unrelated primary work is included.

## PR427 fourth review accepted locally - 2026-11-04

The owner's further CodeRabbit request covers one new inline finding and two
follow-ups on published `f0849584`. The bounded repair adds 10-second inbox
request deadlines, visible failed manual matches, accurate automatic-match counts,
fresh scoped parent ownership for new credit rows, and company/request guards for
server refreshes. Fully handled bank actions opt out of the global error flash;
ordinary API defaults, auth/CSRF and strict ownership remain unchanged.

Independent QA/security accept runtime4 manifest
`8f71c874b0e81eb1fde3291405d668a13d7b08b53319440d491ec8ff831bf1f0`
and source/test10 manifest
`8c24ef15357f2a4268a049c6ff29ea230a3a97c1bb24b52e81658dd7587d6918`.
QA passes 164 repository and 12 temporary cases; security passes 66 repository and
seven native cases with overlapping scopes. Main full1443/164, focused51, author32, lint48known,
typecheck111existing/no-new, build and locales pass with unchanged baselines.
Synthetic adapters do not establish browser/OPFS E2E. Preserve inherited unlocked
import limits and Unknown quarantine. See the review note for exact evidence.
Publish only the reviewed14-path delta, verify the remote SHA and new exact-head
CI; previous CI41747417307 applies only to f0849584. No production access, replay,
merge or deployment. Preserve unrelated primary work.

## PR427 third-round repair independently reviewed - 2026-11-04

A completed review of3f47a24b adds three inline findings and one recovery item.
The retained branch now fixes HTML-only boundaries, action/refresh errors and
persisted unmatched-batch availability. Shared origin locking covers inventoried
expense writers, bank eligibility actions and company/snapshot/share maintenance.
An inert local-only marker enables fresh preconditions and post-handler proofs;
opaque revoked capabilities, explicit fresh owner/parent routing and known/Unknown
separation preserve existing authority, encryption and editable-paid policy.

Final runtime41 manifest:
`a9478719b0e67ac46526bbd8e3db03657c935b656944ac2a37401aac02d76ee7`;
source/test65 manifest:
`5f74ae7b795c4c3786cf4f3123855e64aa186a9a9e725a8535d8da244dd471c1`.
Main full1415/160files passes before the final pure merge preflight; the final
3-case merge regression and35-case affected scope pass after that change.
Full lint48known, current typecheck111existing/no-new, build, locales, API/Pint/
fragment pass with unchanged baselines. Local PHPStan has only inherited optional
missing Scramble in unchanged DocumentationExporter.php. Independent QA311cases,
native40 and PHP323/1287 pass; security104cases, PHP180/641 and native/security50
pass on the accepted scope. Both security and QA renew acceptance of the sole final merge preflight and all
final hashes. QA independently passes48 affected cases /4 files for that delta.

Known limits remain: updated cooperating same-browser clients, inherited unlocked
basic-import owner routing, no browser/OPFS or cross-device uniqueness claim.
The local marker contains no merchant data and is excluded from backup/share/sync.
No server migration, key registration, Store/billing authority or pricing change.
Publish only reviewed paths with both final acceptances, verify committed manifests
and exact remote SHA, then fresh CI. CI41733930901 validates3f47a24b only.
No production access/replay, merge or deployment. Preserve primary unrelated work.
See `EXPENSE_DEFAULTS_BANK_IMPORT_REVIEW.md` for final contracts/evidence.

## PR427 second review fixes verified locally - 2026-11-03

The completed CodeRabbit review of06c837b3 adds four inline findings and three
follow-ups. The same owned branch now fixes compact malformed dates/times,
remittance-only credit amounts, oversized derived VS, Spanish direction wording,
localized matching failures, batched import reads and the literal lock assertion.
Each validated row reserves its hash; all prepared rows insert synchronously,
then every actual id/company/hash is verified in one private context read.
Unexpected actual validation failure aborts the row batch explicitly; unknown
worker outcomes keep the existing quarantine. Fixed queries and matching authority
are unchanged. No additional server schema, infrastructure or policy change.

Full-PR runtime22 manifest:
`4b236b10f2ff2bd403a8287599f0594707a573ff495ab6cfd4e678357c2a7eb1`;
source/test36 manifest:
`b33ad44a7e63e15235aded39654cdeb025d9c0533d3701febfdc898f35b19975`.
Main full frontend1382/158, mounted UI39, scoped bank92, disposable PHP262/1018,
Pint8, lint48known/typecheck112existing-no-new, build, five locales, API documents
and fragment pass. Local PHPStan retains only known optional missing Scramble in
the unchanged exporter. Ten guides and the PR427 fragment match final behavior.
Independent QA/security accept both identical manifests without unresolved findings:
QA frontend131, PHP305/1160 and actual-worker15; security frontend131, PHP177/539
and actual-worker15 pass independently with overlapping scopes. Native adapters
are synthetic, not browser E2E. No new PostgreSQL target is needed or created.
Publish only the reviewed32-path delta; CI41729050967 verifies06c837b3 only.
Then verify the new exact-head CI and resolve the four addressed threads. No production replay, merge or deployment.
See `EXPENSE_DEFAULTS_BANK_IMPORT_REVIEW.md` for the second-round contract.

## PR427 CodeRabbit remediation reviewed - 2026-11-03

The owner reports review findings on published `c79deafd`, PR427. Seven inline
threads and five follow-ups are addressed on `fix/expense-defaults-bank-import`.
Parser fields are bounded to the bank prefix and complete symbols; SQL amount/
currency filters precede candidate locks. Browser reconciliation uses an origin
Web Lock, five fixed unsubscribed queries after legitimate mutation receipts,
post-handler state verification and permanent quarantine for unknown outcomes.
New persisted direction evidence excludes unverified rows even after reload or
explicit batch matching. Explicit delivery null survives both modes; Wise and
shared bridge status apply only current responses. Existing legacy rows, other
devices and old clients retain the documented limits; reload older tabs on rollout.

Final full-PR runtime17 manifest:
`78afb3fdf73ad9f6b30f537684f711ef2914ece59c46f21845096a4aa0e04eb0`.
Source/test31 manifest:
`d52b3e003a8466d669a64ed092af03a8a616843f36dbec024a1bc8972610e686`.
Independent QA accepts these bytes: PostgreSQL251/805 (5 expected skips), true
contention/narrow-lock3/42, focused126 plus final bridge19, actual Evolu/native
SQLite12. Native realm/lock/broadcast adapters are synthetic, not full browser
E2E. The isolated database container is removed. Independent security accepts the identical manifests with no unresolved findings;
its overlapping frontend/backend/actual-worker probes confirm the failure paths.
Publication evidence is recorded separately from local verification.

Main frontend1363/158files, final Wise19, SQLite237/958, Pint8, lint48 known,
typecheck112 existing/no new, five locales, final build and three OpenAPI documents
pass. Local full PHPStan retains only the known missing optional Scramble class
in unchanged DocumentationExporter.php:18; baseline/ratchets are unchanged.
The API inventory changes only the parser hash. Ten guides and one PR427 fragment
reflect final behavior. Prior CI41719918340 validates c79deafd only; verify fresh
CI on the new published commit. No production access, replay, merge or deployment.
Preserve unrelated primary work; the current reviewed artifact is in the retained
expense worktree. See `EXPENSE_DEFAULTS_BANK_IMPORT_REVIEW.md`.

## Owner authorized expense/bank repair publication - 2026-11-03

The owner explicitly requests GitHub push of `fix/expense-defaults-bank-import`
to the owned Satflux origin. Publication includes only the36scoped paths from the
retained expense worktree, based on mergedPR426/686c9304. The reviewed runtime11
manifest07d8edd3...198b29 and source/test21manifest2b843d64...aab4ab remain exact.
Independent QA checks the publication artifact; earlier functional evidence is
retained rather than reported as a new test run. Main owns commit/push and must
verify the remote SHA separately. The local-only entry below records the earlier
milestone; this authorization supersedes its publication gate. No PR creation,
merge, deployment, production replay or unrelated primary-source publication.

## Expense defaults and bank-email reconciliation reviewed locally - 2026-11-03

Owner requested linked defaults and investigation of missing bank movements, then
clarified that today's delivered Tatra notification should pay an existing expense.
PR426 is actually merged as686c9304; older open-PR entries below are historical.
A new `fix/expense-defaults-bank-import` branch retains the bounded candidate in
`storage/app/private/worktrees/superfaktura-expense-import`. Source is local and
uncommitted; no new PR, Git publication, merge, deployment or replay occurred.

New expenses link supplier-number digits to VS and received date to delivery date
until manual/explicit-attachment override. Bank settings and synchronization now
resolve the same selected company with stale-response and overlap guards. Existing
8-16-character recipient tokens survive generation-length drift. New email debits
can pay exactly one eligible same-company expense by exact amount/currency/hints,
with recorded direction distinct from display inference, booking-date paid_at,
atomic linkage/audit and fail-closed debit parsing. Explicit legacy batch matching
uses normalized ledger fields; it does not prove original mail or trigger Store
fulfillment. Cross-device CRDT uniqueness remains outside this guarantee.

Independent QA/security accept the frozen source. Runtime11-file manifest is
07d8edd3af8810cbe369899dfe23c413c2b25761e1438746ef33364543198b29;
source/test21-file manifest is
2b843d64a6cfc2ad7c63294360cd99a38657a1bff5f2586ab7570f1d73aab4ab.
Main full1335frontend/157files, lint48/typecheck112 known ratchets, five locales,
10guide files, build and3OpenAPI validations pass. Author SQLite225/740assertions
and Pint9files pass; independent actualPG225/730 (5expectedSQLite-only skips) and
forced two-connection contention1/16 pass. Final PHPDoc-only impure annotation
preserves the fresh DB recheck and all method bodies/test bytes. Full PHPStan
completes with zero new findings; the sole remaining error is the missing local
optional Scramble class in unchanged DocumentationExporter.php:18. The full
generator has the same dependency limitation; no install or ignore was added. All453API inventory routes and every
other selected source hash remain unchanged; only the parser hash is updated.

Owner-provided production metadata: standalone queue healthy, webhooks0 pending/
delayed/reserved, inbound enabled, today2email rows/latest17:14:08; supplied failed
jobs list contains June/July records. This proves some current server ingestion,
not the selected company's UI or deployed-fix verification. Source history shows
per-company addresses introducedJuly13 while sync retainedJune17 first-company
fallback, supporting the observed mismatch without proving deployment chronology.
Do not retry historical jobs or infer all missing mail is recoverable. Existing
merchant expense/payment state is untouched. Preserve unrelated primary edits,
recovery copies and worktrees. Next: owner source review/publication decision and
separate merge/deployment approval. See feature-branch review note
`docs/development/EXPENSE_DEFAULTS_BANK_IMPORT_REVIEW.md` for contracts and limits.

## PDF recovery and quota follow-up independently accepted - 2026-11-03

After2567fed4 passes CI41687668367, six review findings are fixed: quota
contention, Save/detail guidance, deleted-target retry, visible attachment-only
retry, active extraction dismissal and stale detail continuations. No price,
purchase policy, bulk, schema or encryption change occurs. Quota locks one actual
accounting connection before legacy persistence; ordinary callback failure owns
only fresh-directory cleanup, with uncertain commit/outer rollback left to caller.

Both reviewers accept the same16-source manifest
`1c2d4e95069405f54cb61dbb785db48fa2ecf65d3c43b0cd53bbf756d07b287a`.
Author and independent QA pass PG13/129, SQLite52/360 and focused55frontend;
security passes55frontend/43PHP306. Main full1228frontend/151files, build,
lint/typecheck/locales/changelog/whitespace pass with unchanged ratchets. Local
fullPHPStan has only missing optional Scramble; fresh locked CI remains required.
Test DB/tmpfs/network removed after QA release. Publish on PR426 and verify new
exact-head CI. See [evidence](EXPENSE_PDF_UPLOAD_FIX.md). Preserve primary work
and immutable recovery evidence; no actual import, merge or deployment occurs.

## Individual local expense PDF upload repair independently accepted - 2026-11-03

The owner confirms an individual expense-form attachment triggers a PostgreSQL
UUID error. Published base is79da6582; actual deployment revision is not inspected.
The local Evolu company ID was passed to legacy company-bound ISDOC endpoints,
and local detail refresh/update and new-form attachment persistence had related
server/local mismatches. Implement the accepted company-free parser/quota bridge,
early UUID syntax guard and Evolu-only attachment/draft save/refresh.

Preserve server company ownership, all pricing/quota policy and bulk import.
Local plaintext processing requires confirmation; declining still permits local
attachment storage. Keep the existing384KiB cap, explicit extraction confirmation
and captured company/expense/file generation guards. No shadow company or durable
server file/expense is created. See [contract and evidence](EXPENSE_PDF_UPLOAD_FIX.md).

Author checks pass47PHP/323assertions, scopedPint/PHPStan and32focusedfrontend
cases. Independent security accepts the14-file production manifest after fixing
SEC-PDF-1 with actual Evolu-row ownership and real-adapter regression fixtures;
its separate32frontend and38PHP/269assertions pass. Main lint/typecheck/locales/
build and nine changelog fragments pass with unchanged ratchets. Full-suite
evidence and fresh exact-head CI remain required before completion.
Final QA accepts the same 14-source manifest:
`70fd0a1ee0e34b06694dc2f6ac8dd736eac9166b88fbc94461e7b811d2d45a6b`.
Its 32 frontend cases, 13 independent real-API-builder probes and 47 PHP cases /
323 assertions pass. Security renews acceptance after the final busy-form
guard. Source is frozen; publish reviewed code on PR426 and verify fresh CI.
Main full frontend now passes 1,205 cases across 151 files with no failures.
Fresh full PHPStan reports only the known missing local Scramble class in the
unchanged documentation exporter; no other diagnostic or baseline mismatch.
Retain the immutable handoff snapshot and unrelated primary work; no real import,
PR merge, external repository change or deployment is performed.

## Expense snapshot runtime CI passes; fixture URL guard follow-up - 2026-11-03

Published `6b07e900` passes all four jobs in CI41664475067: PHP2576/13013assertions
with one PostgreSQL-only skip, full PHPStan, frontend1173 and E2E20. Independent
QA/security accepted the snapshot/year repair; all six prior threads are resolved.
The new CodeRabbit test-safety finding4456901528 is confirmed: copied URL or
read/write routing can supersede a raw fixture database-name guard.

Keep production source unchanged. Harden only fixture configuration, effective
and actual target verification, setup/cleanup ownership, and pure synthetic
configuration regressions. Prior runs used preflighted disposable targets.
Require independent final review and fresh exact-head CI after this bounded
test-only follow-up. See [review evidence](EXPENSE_SUMMARY_REVIEW.md).

The final three-test-file guard is independently accepted by QA/security.
Both independently pass nine no-boot unit cases / 59 assertions; QA also passes
PostgreSQL 11 / 155 with the expected SQLite skip. Author SQLite 11 / 124 and
three-file Pint pass. The coordinator verifies the same manifest and unchanged
production/config/dependency bytes, then removes only the disposable test DB
container/tmpfs/network after QA releases it. Publish the reviewed guard and
verify the new commit's complete CI; preserve the immutable recovery snapshot.

## Expense list CodeRabbit snapshot and year repairs - 2026-11-03

Published `a1b1c32b` passes all four jobs in CI41602302146, including
2,566 PHP cases / 12,889 assertions, full PHPStan, 1,171 frontend cases and
20 E2E scenarios. Its four earlier import review threads are resolved.
Two subsequent findings are confirmed: changing year retains an out-of-range
page, and PostgreSQL Read Committed permits summary/page disagreement.

Both year controls now reset page and selection before loading. The backend
uses the actual filtered query connection for summary, paginator and serialized
response within one stable snapshot. Architecture, security and QA accepted
the design before implementation. Owned PostgreSQL transactions use scoped
Repeatable Read / Read Only; supported nested transactions retain caller
ownership through savepoints. SQLite uses deferred committed reads. No auth,
tenant filter, schema, dependency, payment or configuration changes occur.

Author snapshot checks pass on SQLite (11 cases / 124 assertions) and isolated
PostgreSQL 16 (11 / 155), each with one other-driver skip. The old-controller
overlay reproduces all three concurrent-write failures. Related PHP39/259,
frontend80, Pint, lint and unchanged typecheck gates pass. Local full PHPStan
is limited by the available vendor tree's missing optional Scramble dependency;
locked-dependency CI must verify the new head. See the
[snapshot contract and evidence](EXPENSE_SUMMARY_REVIEW.md).
Independent security accepts the frozen five-file source/test delta and passes
SQLite11/124 plus frontend17; independent QA accepts the identical manifest and
passes PostgreSQL11/155 plus three year/stale-response probes. PostgreSQL evidence
is local and opt-in; ordinary CI remains SQLite. No blocking review findings
remain before publication; fresh exact-head CI is still required.
Preserve the primary checkout and immutable recovery snapshot. PR merge,
real expense import and deployment remain outside this follow-up.

## Cross-session expense summary candidate reviewed and adopted - 2026-11-03

The owner supplied a verified 27-file handoff on c9eb45a3. All live/backup hashes,
changed paths and the recovery patch matched before adoption; the immutable
backup remains intact. Adopt the all-filtered-pages expense footer, per-currency
exact totals/counts and owner-selected whole-recorded-document unpaid total.
CSV text/identifier preservation, integer-only masks and dashed aliases address
the four existing CodeRabbit findings. No partial-payment ledger is inferred.

Receiving-session fixes close malformed legacy-currency rendering and five
changed fingerprints at existing Evolu type boundaries, preserving query
selection and the typecheck baseline. Full typecheck now passes112 existing
diagnostics, with no new fingerprints. The company-list query's omitted link
remains a documented pre-existing no-op; no broader sanitizer redesign occurs.

Independent security and QA accept the same18-file production manifest
8ef82904ed314a56bd610b92a2ad23b78eb6e2598e7021fdeeb087050333f120.
Fresh author PHP39/259assertions, scoped Pint/fullPHPStan; main frontend1171/
149files, lint/locales/build/API; QA33frontend/35PHP/247assertions/four browser
scenarios; security79frontend/26PHP/193assertions/23extra assertions pass.
See [contract and evidence](EXPENSE_SUMMARY_REVIEW.md). Publish on existing PR426
and verify fresh exact-head CI. Primary edits, backup and other worktrees remain
preserved; no real data import, PR merge, billing change or deployment occurred.

## Expense import CI baseline mismatch corrected - 2026-11-02

CI41539349631 on a64f0bed passes all2555 PHP tests/12812 assertions and Pint;
the PHP job fails later on PHPStan's single unmatched arrayFilter.same ignore
for BusinessExpenseExcelImportService. Earlier scoped analysis did not catch
this full-project baseline mismatch. Remove exactly that obsolete one-count
entry; independent QA verifies all remaining bytes/counts and1615 source/test/
frontend/config/dependency files unchanged. Baseline decreases625->624entries,
825->824 ignored counts. Level5 and unmatched-ignore enforcement remain intact.
The exact full PHPStan command reproduces the failure before and passes after
the removal in the verified disposable target. Push and fresh exact-head CI are
next; no app behavior, new payment, real import, PR merge or deployment changes.

## Expense import PR conflict resolution - 2026-11-02

The owner requested repair of PR #426's three documentation conflicts after
master advanced to `86e77e81` with merged #424/#425. Synchronize master into
`feat/superfaktura-expense-import`, retaining both sets of project records.
The manual resolution touches only PROJECT_STATE, ROADMAP and DECISION_LOG;
expense source, tests, locales and user guides stay byte-identical to reviewed
`be19843d`. Upstream code enters unchanged through its existing master history.
No product behavior, private data, real import or deployment is changed.
The PR remains for owner review; conflict repair does not merge it into master.

## SuperFaktura expense import implemented and independently reviewed - 2026-11-02

The owner explicitly requested expense import in Satflux. A dedicated
`feat/superfaktura-expense-import` worktree starts at local origin/master
`9dfc4166`, preserving the dirty primary checkout and all other retained work.
Real frontend/backend specialists implemented local/server parity; independent
security and QA accept the frozen source without blocking findings.

Company Import now offers invoices and expenses. The current 33-column export
maps automatically, preserving original numbers/dates/totals and extra metadata
in private notes. Partial/cross-currency payments remain recorded for review.
The modal prevents overlapping/stale operations, loads current duplicate state
before writes and keeps results visible. Historical results open all years.
No schema, auth, billing, entitlement or external repository changes occur.

Coordinator-only private dry run accepts all 52 rows in an in-memory fake; no
actual expense or private workbook fixture/log was saved. Independent QA passes
37 synthetic cases; security passes 38 focused cases and 33 extra assertions;
both pass 15 PHP cases/116 assertions. Coordinator related PHP passes51/294 and
documentation8/26, scoped Pint/PHPStan, lint/typecheck ratchets, five locales,
build, API contracts/inventory and whitespace. The final constrained full
frontend run passes1,132 cases across147 files with two workers; initial
unconstrained timeout failures resolve without source changes. See the
feature-branch `EXPENSE_IMPORT_IMPLEMENTATION.md` for verification and limits.

Retain source in `storage/app/private/worktrees/superfaktura-expense-import`.
No publication, app import, merge or deployment. Explicit-number rerun protection
is company-scoped; rows without internal numbers create new entries on rerun.
Next: owner review of the preserved local source; later publication, merge and
deployment need applicable owner authorization.

## Chorala changelog CRLF recovery independently accepted - 2026-11-02

Public GETs confirm that the configured feed stops at PR #406. The next eight
Satflux changelog synchronizations failed because the two managed published
bodies contain CRLF while their immutable source/journal hashes use LF. Other
fields and source hashes agree. The journal still records two created drafts,
with no deploymentSha; successful Chorala image CI is a separate pipeline.

The comparison-only CRLF/LF repair preserves raw bodies, hashes, IDs and
publication guards. Missing notes for merged PRs #411, #413 and combined #415
bring the inventory to eight. Author and independent QA pass all 71 offline
cases; QA reproduces baseline failure and fixed two acknowledgements/six drafts,
zero publication PATCH, plus 11 adverse scenarios. Independent security accepts
92 cases/310 assertions, including both historical hash goldens. Source syntax,
fragment schema/source uniqueness and whitespace checks pass. New-head CI remains
separate. See [incident and recovery evidence](CHORALA_CHANGELOG_RECOVERY.md).
Original primary edits and retained worktrees are preserved. No production
mutation, workflow retry, merge, deployment or notification was performed.

## Exact Platba bank-description compatibility in progress - 2026-11-01

The owner obtained a bank-direct private copy and supplied the fixed public
operation label Platba with the existing numeric-description structure and a
QR reference of 32 hexadecimal characters. The tail has only already-supported
counterparty/signature/URL/notice labels. Actual accounts, references, amounts,
dates and personal values are excluded from source, fixtures and reports.

Implement only the exact third description alternative Platba, retaining all
existing full-frame, numeric, reference, bank-proof and provider guards. Old
CCINT/CSO output and fingerprint goldens must remain byte-identical; Platba keeps
its own literal in the unchanged v1 tuple, without aliasing to another type.
Backend owns the parser and bounded existing unit/native/MIME tests. Independent
QA/security accept the frozen four-file scope without findings. Author passes
159 tests/719 assertions and scoped Pint; QA passes 338 bank tests/1904 assertions,
three private ingress tests/17 assertions and 39 pure profile checks. Qualified
provider replay checks pass three existing shipped tests and four private cases/
22 assertions against the actual unchanged DLL. Security passes 81 targeted tests/
346 assertions and 839 independent checks. Main full PHPStan/API checks, source
hashes and 20 unchanged boundary hashes pass. Main owns publication and current
exact-head CI/review remains required; all isolated test databases are stopped.

Provider e8d7fbc read-only evidence resolves the initial label-only replay concern:
reference is a globally unique immutable invoice/Store key; row lock and confirmed
request check precede event-key processing; recorder ID is the same reference.
The same signed reference cannot settle twice merely because the label changes.
A cross-invoice rendition also needs the bank to authenticate a different valid
reference for one credit, which is unestablished. Independent security classifies
that as an informational baseline bank-reference-stability assumption, not a
verified new vulnerability. Contract-v3/alias-table work is unnecessary and not
implemented; provider, package and database remain unchanged. See the profile
contract in the feature worktree. Actual settlement remains a runtime gate.

## Authenticated bank mail reaches incomplete-credit review - 2026-11-01

The owner independently made a new real payment and reports a 20:15:06 UTC audit:
sepa.bmail_review_required / incomplete_credit_evidence, no verifier_reason. At
reviewed source 12f83c11 this branch proves parsed credits and the sender/origin
gate passed, but none satisfies all five existing evidence predicates. It returns
before API-key/settings lookup or payment reporting. The audit alone does not
identify the MIME body profile or exact failed condition; deployment SHA remains
unverified. The owner confirms only Mailgun logs exist for this new message.
The empty earlier capture and completed job cannot reconstruct the credit frame.

A metadata-only follow-up is in progress on the existing scoped repair branch.
It adds bounded fixed eligibility booleans and a pure first-failing Tatra profile
stage, sharing the exact existing parser validation. No bank values, new raw
retention, eligibility change, capture extension or financial replay is accepted.
Backend implementation passes 146 PHP tests/526 assertions and scoped Pint.
Independent QA passes 315 repository tests/1774 assertions, 24 private tests/233
assertions, 359 baseline checks across 116 cases and the operator-query test/15
assertions. Security accepts the frozen
four files with 59 tests/224 assertions and 450 baseline checks across 90 inputs.
Main full PHPStan, API export/check, 19 preserved-file hashes and diff checks pass.
No unresolved source finding remains; corrected operator projection selects one
Store and the incomplete-credit reason. Fresh exact-head CI/review remains a
publication gate. See the diagnostic contract and review artifact. Do not request another real transfer; new diagnostics cannot backfill
this historical audit. Graph lacks current parser/capture edges; targeted reads
were used without a rebuild. Prior published checks remain historical evidence.

## Tatra ASCII wire-body repair independently accepted - 2026-11-01

Owner-run capture facts isolate the current rejection to unsigned MIME controls
despite passing bank DKIM/core headers/full-body proof. A separate format probe
reports plain UTF-8/7bit ASCII, no controls and relaxed/relaxed. No private bank
body/values were received by agents. The new narrow profile binds one fixed
canonical ASCII body to the same proof's hashes/lengths rather than allowing
unsigned MIME decoding. Legacy fully signed proof takes precedence unchanged.

Based on 406e1fef, the candidate passes 91 PHP tests/991 assertions and 77 Node
tests. Independent QA passes 256 PHP tests/1550 assertions, 19 private tests/197
assertions and 666 projection checks. Security passes 91 PHP tests/991 assertions,
6011 helper checks and 38 crypto checks. PHPStan/Pint/API checks pass; nine source
files and 18 preserved-file hashes
manifests are in the [review artifact](artifacts/tatra-ascii-wire-2026-11-01/README.md).
Safe pure outer body/frame diagnostics preserve the original v1 capture and TTL;
they do not import/report/replay a payment. No parser/dedup/provider/history change.

Publish the follow-up on the existing PR #415 branch, then verify fresh exact-head
CI/CodeRabbit before owner rollout. A compatible read-only recheck can qualify
the actual retained transaction frame if unexpired. Actual settlement remains
unverified; no new transfer, merge or production action is performed by this work.

## Approved private bank MIME capture ready for CI - 2026-11-01

Owner-approved proposal ba1177a5 is implemented default-off on the separate
feat/bank-mime-private-capture branch, based on037a6fa7/PR414. One encrypted
Redis7 slot is explicitly armed for one local Store and an absolute two-hour
deadline; only an existing matching unexpired ARMED slot can become CAPTURED.
Missing/evicted state cannot re-arm, expiry cannot extend and safe operator
diagnostics cannot import/report/replay a payment. Strict bank proof is unchanged.

Independent QA243/1267 plus private15/285 and Node56 pass; security54/475 plus
36 private Node assertions accepts the frozen15-file manifest without findings.
Author128/917, Node56, PHPStan/Pint and main API/hash checks pass. CI includes a
disposable Redis fixture for real concurrency/expiry checks. See the [review
evidence](artifacts/bank-mime-capture-implementation-2026-11-01/README.md).

Fresh exact-head CI remains required. No production activation/deployment or
real bank capture occurred; actual Tatra qualification and the historical
payment remain unresolved. Existing backup retention applies to encrypted
residuals. The unrelated primary checkout and prior branch remain preserved.

## PR414 review follow-up: one-route bank-mail compatibility - 2026-11-01

The owner confirms Mailgun Free, permitting one inbound route. PR414 at initial
`12e00fa4` passed all remote CI, including Node22, but CodeRabbit identified three
valid follow-ups. The revised receiver ignores unusable optional sender metadata,
checks the configured Node executable in rollout instructions, and withdraws the
optional compact-date normalization that could suppress distinct same-day credits.
Parser/unit/dedupe/importer bytes match merged base `c90caa40`; legacy compact
processing-time/retry ambiguity remains documented rather than changing policy.

Shared MIME intake keeps server-selected Store strict verification separate from
Company extraction, encrypted metadata and final owner binding. Author follow-up
passes 155 PHP / 623 assertions, independent QA 162 / 677 (including 7 private
controls), and security 50 / 334 (including 10 private controls). Runtime 42 cases
and 12 probes previously passed at unchanged bytes. Pint, full PHPStan, API/OpenAPI,
locale parity and hash checks pass. See [final review evidence](artifacts/bank-mail-single-route-review-2026-11-01/README.md).

The isolated `fix/bank-mail-single-route` worktree preserves unrelated primary
changes. PR414 is temporarily draft pending revised-head publication/CI. No
provider/package/dependency/plan/billing/UI change, merge or deployment. Deploy
compatible web/queue workers before switching the one EU route to `.mime`.
Original EML retrieval returned404 on both endpoints; current bank signing/layout
and the existing payment remain unverified. No additional real transfer is a test.

## Wallet drift hotfix reviewed; bank-origin incident open - 2026-11-01

GitHub confirms consumer PR412 merged at `9c329264` and provider PR45 at
`9c6d7b30`; deployment is owner-reported, with actual runtime revisions unverified.
A production excerpt confirms a SEPA_INSTANT-only wallet false alarm and a later
bank-mail `unverified_bank_origin` review. The wallet repair excludes only that
activation flag from both current and legacy comparisons, preserving crypto
monitoring, saved baselines and fresh-read/lock requirements for resolution.

Independent security and QA accept the frozen two-file patch. Focused author
suites pass 35 tests / 247 assertions; independent QA passes 38 / 323 including
private diagnostic controls. Full PHPStan and scoped Pint pass. See the
[incident and recovery record](WALLET_SEPA_DRIFT_INCIDENT.md) for acceptance,
reviewed hashes and operational limits. No UI/plugin/package change is included.
Published [PR #413](https://github.com/webiumsk/satflux/pull/413) on
`fix/wallet-drift-sepa-method`; exact-head CI is pending. No merge or deployment
is performed.

The bank payment remains unconfirmed. Parsed-only Mailgun intake cannot establish
bank origin; the actual route and runtime revision still need verification. No
current original bank message is available. Route recovery, actual template
qualification and existing diagnostic gaps remain separate from the wallet fix.

## Ready for source review: automatic Tatra B-mail via Mailgun - 2026-10-31

The owner rejected permanent manual-only operation and selected Tatra banka via
existing Mailgun intake. This candidate adds original-MIME intake, encrypted
queue payloads, independent local DKIM verification and a strict historical Tatra
plain-text profile with a positive real-parser reporting path. It preserves
original account/amount/currency checks, QR case, unknown-outcome review, generic
company imports and FIO/NOP. Canonical credit identity excludes invoice reference
and rendering; conservative collisions cannot prove a second invoice paid.

[Evidence](artifacts/tatra-bmail-automation-2026-10-31/README.md) records final focused
PHP158/687, independent PHP108/579 plus output-cap1/2, Node26 real-crypto cases,
security acceptance of24 source files plus the deployment installer, and QA41
hashes. PHPStan/Pint/API/OpenAPI/locale checks and existing lint/typecheck ratchets
pass. Deployment guards pass53 after a subprocess-capable rerun. The root npm
manifest is unchanged; isolated locked verifier dependencies are installed by the
normal deployment script. No deployment command was executed.

A real current Tatra message is unavailable locally. Historical witnesses and
synthetic QR/keys/DNS qualify the bounded implementation, not actual bank signing,
current layout or active Mailgun routing. The operator must verify that coverage
and approve deployment/Mailgun .mime configuration separately. Prior manual-only
and alleged-reference-theft claims are superseded by the candid design/security
record. Fresh exact-head CI follows source publication; no merge or real funds.

## SEPA isolation integrated on merged 2.4.7 consumer - 2026-10-30

The owner authorized SEPA integration and plugin source publication. This branch
applies reviewed consumer `885168f2` to merged `4ff5c32a`, with five-locale docs,
truthful bank-format hints and a stable-identity provider-failure retry regression.
Companion provider combines compatibility and isolation as repaired SEPA 0.8.2.0
for exact BTCPay 2.4.7. [Integration evidence](SEPA_247_INTEGRATION.md) records
contracts, independent reviews, 2,253 PHP / 1,094 frontend passes and all local
gates. Independent provider QA passes 165 cases with zero skips. Final provider
archive and fresh repository-specific CI remain distinct gates. No production
operation, merge or deployment is included in this source publication.

## PR411 incremental review portability fixes - 2026-10-30

CI and CodeRabbit completed successfully on `0e0098db`; both original findings
are resolved. Three new minor comments require only review-evidence clarity and
browser-test portability. The follow-up labels the one-off component fixture and
old manifests as historical, formats reviewer identity as an agent identifier,
and delegates browser selection to Playwright unless an explicit override is set.
Independent QA passes 19 launch-option checks and 121 actual native assertions;
syntax/ESLint/diff checks pass. No product source changes or browser installation.
Fresh checks must cover the resulting published head. Documentation coverage is
40% against 80%, a separate advisory. See [evidence](../BTCPAY_2_4_7_IMPLEMENTATION.md#incremental-review-of-0e0098db).

## PR411 CodeRabbit follow-up - 2026-10-30

The published compatibility revision `7bb1677d` passed remote CI. Both actionable
CodeRabbit findings are valid: payment-sound URLs still accepted FTP, and mounted
inactive Payment controls could prevent unrelated saves for legacy durations.
The follow-up restricts sound URLs to HTTP(S), disables inactive/inaccessible
bounded controls and omits only invalid inactive durations. Preserve valid and
nullable values, active range/step validation, entitlement stripping and existing
minutes/seconds conversion. See [review evidence](../BTCPAY_2_4_7_IMPLEMENTATION.md#pr411-coderabbit-follow-up).
Fresh exact-head CI remains separate from prior success. No merge or deployment.

## BTCPay 2.4.7 consumer compatibility implemented - 2026-10-30

The owner authorized implementation and publication of the compatibility branch.
[Implementation and validation](../BTCPAY_2_4_7_IMPLEMENTATION.md) cover settings
seconds/minutes, safe CSS/redirect URLs, accurate Pay Button buyer email and
numeric Crowdfund recurrence on create/update. All five locales and feature docs
are updated. Full PHP passes 2,193 tests/10,571 assertions; frontend passes 1,080.
Pint, PHPStan, API documentation, lint/typecheck/parity/build pass with existing
warning/deprecation baselines retained. Independent QA/security accept core source.

Provider changes and local packages have a separate repository lifecycle. Preserve
SEPA isolation and conditional core/Raffle schema inventory gates; no merge,
production package installation or deployment follows from source publication.

## Blink probe diagnostic publication candidate - 2026-10-30

The owner authorizes publishing the independently reviewed diagnostics in a
separate `fix/blink-probe-diagnostics` branch from `5cc0688a`. Only the prober,
its synthetic privacy regressions and scoped documentation are included. Preserve
separate SEPA work and unrelated local edits. The owner reports local Blink
connection success and will perform live deployment; the live failure cause is
still unverified. See [the runbook](BLINK_PROBE_DIAGNOSTICS.md) for safe logs,
66-test evidence, scoped static checks and the full-local-PHPStan dependency limit.

## Wallet checkout PR review follow-up (2026-10-22)

Published consumer [PR #399](https://github.com/webiumsk/satflux/pull/399) at
`eae6f32f` passes PHP and Playwright CI; Node stops at the production dependency
audit before lint/tests/build because locked DOMPurify 3.4.16 is affected by newly
reported advisories. Review follow-up updates only DOMPurify to the patched 3.4.17
while preserving every other locked dependency; security thresholds stay intact.
No demonstrated Satflux XSS or production exploitation is claimed.

CodeRabbit's consumer finding is valid: the read-only Cashu query now reports total
matches and truncation while keeping payment output capped at 20 rows. Provider
[PR #42](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/42) at `f3c7087`
has green compatibility CI. Its valid description finding is corrected for an
LNURL-only store without granting an outbound payout adapter. Provider tests also
explicitly compare the persisted original snapshot before/after unchanged re-enable.
The earlier local 1.4.0.8 archive predates this description follow-up and is retained
unchanged; repository review does not rebuild or publish that package.

Follow-up verification passes 11 author/6 independent provider tests, four SQL
boundary cases, seven patched-version sanitizer checks and the production build.
The patched-lock audit passes with zero critical findings (6 high/35 moderate
remain). New provider commit `dd230ca` passes CI; its incremental CodeRabbit
review is quota-limited. Source security and independent QA accept the follow-up.
Follow-up verification and remaining gates are recorded in
[the contract](WALLET_CHECKOUT_CASHU_POLICY.md). Exact new-head CI and incremental
CodeRabbit availability must be checked after publication. No merge, deployment,
production access or unresolved-funding override is included.

## Wallet detection and Lightning-exclusive Cashu checkout (2026-10-22)

Owner-authorized fixes are isolated in Satflux `fix/wallet-detection-cashu-checkout`
from `5942032a` and owned plugin `fix/cashumelt-checkout-settings` from `6bdd688`.
The owner explicitly requires Cashu hidden whenever any Lightning wallet is
configured, including API wallets and Boltz. The owner reports installed CashuMelt
1.4.0.7 and later confirms the first invoice settled directly through Lightning;
its separate Cashu funding quote remains unknown after HTTP 400. No production
access or Cashu-fund outcome has been verified.

Detect wallet input on blur/manual action with current-candidate and async guards.
Suppress new Cashu prompts/empty placeholder activation using configured Lightning
presence, while preserving exact store/invoice/quote-bound old prompts. Candidate
CashuMelt 1.4.0.8 permits identical-target re-enable under the existing PG lease;
Satflux preserves owner/email grants, encrypted intent and Lightning methods on
that availability-only path. Destination/risk changes retain existing guards.
No settlement rewrite, schema, new environment variable or BTCPay core change.

See [the implementation contract](WALLET_CHECKOUT_CASHU_POLICY.md) and
[read-only funding runbook](CASHUMELT_FUNDING_READONLY_RUNBOOK.md). Both scopes pass
independent security review. Main passes 260 PHP tests/1,794 assertions, full
PHPStan and changed-file Pint. Frontend passes related 88-case coverage, final
17-case focus, lint/typecheck/parity/build. Independent QA passes 21 PHP/178
assertions, 17 checked-in Vue cases and five additional lifecycle cases.
Versioned provider author suite passes 163/163, zero skipped; independent provider
QA passes 22/22 and the read-only SQL smoke. Frozen hashes and package identity
are recorded in the contract. Existing host dependency advisories remain inherited.
No push, release, installation or deployment follows from these local fixes.

## Wallet attestation probe monitoring repair (2026-10-22)

The owner reports an outstanding-invoice wallet-change hold on a new store with
no manually created invoice. Read-only tracing against Satflux `fe0ace6e` and
BTCPay 2.4.5 confirms automatic payee probes used one-minute expiration but
inherited the store monitoring duration (normally one day); archival does not end
monitoring. The actual production blocking invoice has not been inspected.

The isolated `fix/wallet-probe-monitoring` candidate explicitly requests one minute
of monitoring for future probes only. Keep every wallet-change guard unchanged.
Existing invoices retain original deadlines; no metadata/archive/amount exemption,
core/plugin/schema/environment change or production mutation is included.
See [WALLET_PROBE_MONITORING](WALLET_PROBE_MONITORING.md) for acceptance, read-only
operator steps, rollback, review/test evidence and provider-expiry limitations.
Five user-documentation locales explain the hold. Tickets PR #397 and its working
tree are separate; this branch starts from the current default branch.

Independent security accepts source digest `4505e84b69cb37c4385f128f25b652822b84b5f8415aadc966473c4dae2dc767`
without blockers. Independent QA passes 24 focused tests/150 assertions after
verifying all 167 installed dependencies against the lockfile. Main's broader
verification passes 151 PHP tests/768 assertions plus 8 documentation tests/26
assertions, changed-file Pint and full PHPStan. Details are recorded in the contract.
Representative direct-Lightning/LNURL
expiry validation remains a deployment acceptance gate. No current production
store has been unblocked or verified by this repository investigation.

## Tickets media and passwordless migration repair (2026-10-20)

Owner-authorized repository fixes are in `fix/tickets-media-passwordless` from
Satflux `5e6953cf`; see [TICKETS_MEDIA_PASSWORDLESS](TICKETS_MEDIA_PASSWORDLESS.md).
Correct nested ticket-type mutations, remove the privileged Files path from ticket
images, stage image changes until Save and retain IDs on partial failures. The owned
Tickets candidate 2.0.4 returns actual scoped logo URLs and allows 2 MiB raster images;
install/verify the provider before the dependent consumer. Preserve grouped QR emails.

The owner explicitly retains a temporary password migration entry for older accounts.
Disable reset emails and both reset POSTs (uniform 410), redirect both historic reset
URL formats server-side, and preserve guest/recovery/passkey/legacy migration login.
Reported self-addressed reset emails are consistent with the old Laravel endpoint;
the caller is unknown. No production behavior or deployment was verified.

Local evidence: 44 focused PHP tests pass, with a final overlapping nine-test reset/
migration rerun; independent QA passes 31 frontend tests; provider passes 24 tests.
PHPStan, Pint, lint, typecheck, locale parity and final build pass. Tickets 2.0.4.0
is packaged locally; its DLL matches the tested Release DLL. Browser discovery passes;
actual browser execution and persisted provider image lifecycle remain staging gates.

Independent security found no high/medium blockers; QA's deletion-before-Save finding
and security's missing old-query-link redirect are closed. Tests/checks and remaining
staging gates are recorded in the contract. No new environment variable, production
change or deployment is included. Incident PR #395 was absent from the initial
ticket base; the integration follow-up below records its subsequent owner merge. Graphify's query graph was unavailable; targeted source reads used.

### Authorized Satflux publication follow-up

The owner authorizes committing and pushing the Satflux repair branch to the owned
`webiumsk/satflux` repository. The reviewed 31-file source/test fingerprint remains
`5b0b8c410790be6ef6ef9b37f91cfe3c8557f9713c74dce3571f10752ce0f83d`;
only publication documentation changes after review. Publish the consumer separately
from the locally prepared Tickets 2.0.4 provider. Exact-head CI and GitHub review,
provider publication/installation and staging acceptance remain next gates. No merge
or deployment is authorized by the push request. Preserve the unrelated image edit
in the original checkout and exclude disposable environment files/artifacts.

### PR #396 PHP CI compatibility follow-up

PR #396 head `12bb3d2b` passed Node and Playwright CI, but the PHP suite failed
five obsolete `AuthTest` password-reset expectations while 1,831 tests passed
(8,391 assertions). [CI run 39469452552](https://github.com/webiumsk/satflux/actions/runs/39469452552)
is the source of these results. The approved reset contract is HTTP 410 with no
mail or credential changes; five general-auth tests still expected 200/422, mail
or a successful reset. Update those cases without changing application source,
retaining registration, login/logout and email-verification assertions. Add explicit
no-notification/no-token/no-login and hash/session/valid-token preservation assertions.

Isolated offline `AuthTest` plus `PasswordResetLinkTest`: 25 passed, 118 assertions;
changed-file Pint passes. Independent QA accepts the test-only delta. Fresh
exact-head CI remains required; do not treat the initial failed run as acceptance.

At follow-up head `2c783de1`, [CI run 39480617117](https://github.com/webiumsk/satflux/actions/runs/39480617117)
passes all 1,836 PHP tests (8,425 assertions), Pint, Node and Playwright. PHPStan
then identifies exactly three obsolete baseline ignores in the changed ticket
controller/service code. Remove only those unmatched entries, without regenerating
or expanding the baseline or disabling unmatched-ignore reporting. The earlier
changed-file analysis did not enforce this whole-project baseline check. Full
local PHPStan passes with no errors; independent QA accepts exactly the three-entry
removal. Fresh published-head CI is required for final acceptance.

### Ready-for-review CodeRabbit follow-up

At `9bc086fe`, [CI run 39482230312](https://github.com/webiumsk/satflux/actions/runs/39482230312)
passes PHP (1,836 tests/8,425 assertions), full PHPStan/Pint/Composer audit, Node and
Playwright (20 passes). The owner marks PR #396 ready for review. CodeRabbit reviews
this exact head and posts one minor actionable finding plus two terminology nits.

The persisted event must remain visible/count toward limits after an image failure,
even if the form is cancelled or list refresh fails. Upsert the returned creation
into local events, refresh after a create/image partial failure, and show the image
notice after refresh because loading clears notices. Retain the event ID and pending
file for retry. Add regressions for successful/failed refresh plus Cancel/limit and
retain the existing no-duplicate retry test. Align SK "podujatie" and DE "Event".
Independent QA accepts this bounded frontend delta and independently passes all
13 focused frontend tests. Full lint (zero errors/48 existing warnings), typecheck
(120 unchanged diagnostics), locale parity and production build pass. Fresh-head
CI/review remain required.
No backend, auth, provider, billing or production behavior is changed by this follow-up.

### PR #396 integration after owner merge of #395

The owner merged #395 into `master` at `d37153ce` (2026-10-20) and requested resolving
#396's conflict. Integrate that base into `fix/tickets-media-passwordless`, preserving
both decision-log append sections in chronological order. All application changes
combine automatically; the only manual conflict is `DECISION_LOG.md`. Keep both
PHPStan baseline reductions and image-route throttles. This branch update does not
merge #396 into master or alter production/provider state.

Prior ticket head `cfa0467f` has green [CI 39485258893](https://github.com/webiumsk/satflux/actions/runs/39485258893):
1,836 PHP tests/8,425 assertions, Node and 20 browser passes. Its original CodeRabbit
finding is resolved; incremental review was quota-limited despite a successful check.
Independent integration QA accepts preservation of both parents and all decision
sections. Combined offline checks pass: 128 PHP tests/507 assertions, 13 frontend
tests and full PHPStan with no errors. Fresh published-head CI remains required.

## Cashu recovery API export compatibility repair (2026-10-16)

The owner reports refused Satflux change export while native BTCPay export works.
Source diagnosis at Satflux `35f5e414` / CashuMelt `3486115a`: MVC `Controller`
inheritance makes the exact BTCPay 2.4.5 global UI antiforgery filter require a native
form token for API POSTs. Satflux sends correct JSON and the merchant key.
The bounded fix and acceptance criteria are in
[CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md): `ControllerBase`, plugin 1.4.0.6,
actual-host filter regression and exact consumer request assertions. Preserve
API/store/email-code/privacy protections and native UI/core/third-party source.

Provider PR #38 is owner-merged; Satflux PR #394 remains open at the initial check.
Prepare a separate provider PR/package. Independent security/QA, native regressions
and exact-head CI precede handoff. No production state was inspected or changed;
the reported balance and native export success are owner-provided evidence.

## Cashu funding-lookup recovery repair (2026-10-16)

Continue the owner-authorized owned CashuMelt/Satflux recovery work. The owner
confirms CashuMelt 1.4.0.4, native retry HTTP 400 and exactly two FAILED records.
The safety count of three obligations does not establish a third failed payment;
terminal records can retain recovery material. Source review identifies funding
polling before durable recovery, insufficient blocker metadata and a worker retry
counter reset. No actual merchant evidence category or recoverable balance is known.

The bounded contract is [CASHU_PAYMENT_RECOVERY](CASHU_PAYMENT_RECOVERY.md), candidate
plugin 1.4.0.5. Reuse existing restore/exact-payout paths with validated original
issuance/proof binding; unknown funding cannot establish unpaid/settled status.
Preserve all holds without sufficient evidence. Add read-only blocker categories,
phase/reasons and monotonic automatic retries under the quote lock. Core, third-party
plugins, billing, configuration and production remain unchanged. Main owns contracts,
integration/Git and guides; specialists have disjoint plugin/backend/frontend scope.

Baseline Satflux `94a3f593` is independently accepted with green
[CI 38829804278](https://github.com/webiumsk/satflux/actions/runs/38829804278):
1,744 PHP tests/7,657 assertions, 793 Vitest cases, 20 browser passes/three expected
skips, static/build gates and fresh locked HttpFoundation 7.4.21. Existing production
npm findings remain 40 (36 moderate/four high), permitted by the unchanged threshold.
Provider baseline `b86e6193` is the reviewed 1.4.0.4 candidate in Webium PR #37.

Source implementation and independent security review are complete: consumer
11-file manifest `6866913178facfcea23cc5bcfa71d6871328c1781a23a5c61edf7e9f5183d296`,
provider six-file manifest `1ee6a9ace00056766d1401be382abe93c4b1fe3f1a10a1d62cb1004af360ed8e`.
Independent QA repeats 124 focused PHP cases/900 assertions and the final 76-case
frontend focus. Four-file Pint, two-file PHPStan, typecheck (121 unchanged baseline
diagnostics), lint (zero errors/48 warnings), five-locale parity and production build
pass. Author full Vitest passes 815 cases before compact markup/compatibility fixes;
final focus covers those changes. UX source review accepts the compact native
disclosures. Plugin/test compilation passes with existing warnings only; native
runtime/independent QA, package identity and exact published-head CI remain required.
Existing PRs record final revisions/results. Use verified disposable SQLite/PostgreSQL
and synthetic mints only. No production or real recovery is implied; the owner's
actual outcome remains unknown until safe staging evidence.

## Authorized Cashu connection preflight follow-up (2026-10-16)

The owner requests wallet-change blockers on the Cashu connection/settings screen
before editing or saving. The bounded contract is the connection-screen follow-up
in [CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md): an optional aggregate on the
existing settings read, persistent advisory status, recovery navigation and a
safety-only refresh that retains edited fields. No new mutation, authorization
shortcut, provider change, configuration or production operation is included.

Baseline `fec8a92353ca974d1d01bc35b37712b483a2de1d` is published in PR #394 and
independently accepted with green
[CI 38826049379](https://github.com/webiumsk/satflux/actions/runs/38826049379):
1,737 PHP tests/7,600 assertions, 778 Vitest cases and 20 browser passes/three
intentional promotion-fixture skips, no retries. All static/build gates pass and
fresh PHP CI installs HttpFoundation 7.4.21. Composer audit has no advisories.
The existing production npm audit reports 40 affected packages (36 moderate/four
high), identical to baseline; its unchanged critical-only failure threshold permits
success. JavaScript dependencies are unchanged. Do not describe that audit as clean.

Implementation source is complete; independent security and UX reviews accept the
eight-file production manifest `682b2c895ff99ff496f6a7d6e799f4e3c95c17339d2c082f355250eb4580f0fd`.
Independent QA confirms the same source scope and passes focused PHP (25 cases/122
assertions). Author frontend focus passes 47 cases and full Vitest passes 793 cases
in 124 files. Targeted Pint (two PHP files), controller PHPStan, AST graph update and
documentation validator pass. At this source snapshot, final frontend static/build
checks, independent frontend focus and exact published-head CI remain required;
the existing PR #394 records final completion evidence. No dependency, baseline,
workflow or configuration changes. CashuMelt 1.4.0.4 and its reviewed package remain
unchanged; coordinated staging/merge/release/deployment approval is separate.

## Authorized Cashu change recovery integration (2026-10-16)

The Product Owner explicitly clarifies that the owned CashuMelt plugin may change
alongside Satflux. BTCPay core and third-party plugins remain read-only. This
supersedes the earlier blanket provider restriction only within this owned-plugin
scope. No production access, real fund operations, merge, release publication or
deployment is authorized.

Satflux starts at `c652fdbd299ded7c42165ea894cafa72732c743f`, whose
[CI 38819190804](https://github.com/webiumsk/satflux/actions/runs/38819190804)
passed and was independently accepted by QA: 1,690 PHP tests/7,188 assertions,
748 Vitest cases, 20 browser passes/three intentional skips/no retries, static
checks, build and audits. Both PHP jobs freshly installed HttpFoundation 7.4.21.
The owner subsequently supplies diagnostic evidence of unresolved change with
completed payment rows; native recovery already exists but has no Greenfield API.

The approved implementation contract is
[CASHU_CHANGE_RECOVERY](CASHU_CHANGE_RECOVERY.md). It adds an aggregate balance/state
API plus guarded export/verification to CashuMelt and a six-digit-code-protected
owner recovery flow to Satflux. Implementation and frozen-source independent
security/QA review are complete with no blockers. Full independent PHP passes
1,737 tests/7,600 assertions, Pint 1,041 files and PHPStan reports no errors.
Full frontend passes 778 cases, final independent focus 30 cases, actual typecheck
retains 121 baseline diagnostics, lint has zero errors/48 existing warnings, and
five-locale parity/production build pass. The AST-only Graphify update completes.
Provider base is owned Webium main `79de74f4fd9f770539dd30ef29b7b8e6abdfe7b0`,
branch `feat/cashu-change-recovery`, candidate CashuMelt 1.4.0.4. Satflux continues
on `feat/free-pro-access`. Existing host pin, recovery services, locks and safety
refusals remain authoritative. Provider graph is unavailable; targeted source
inspection supplements Satflux Graphify queries. Publication and operational
readiness must be assessed separately in both repositories. Plugin commit
`b86e619380fbbbf1427a3e47124d30b369b78d94` is published in
[draft PR #37](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/37), with
independently verified green
[CI 38824816578](https://github.com/webiumsk/BTCPayServerPluginsWebium/actions/runs/38824816578):
nine builds, 583 maintained-plugin tests including 129 CashuMelt cases, zero skips.
Independent QA verifies the local 1.4.0.4 package matches the tested DLL and includes
no core/other-plugin assemblies; hashes are in the contract. Exact-head Satflux CI
remains required after candidate publication. No real-host installation or wire-level
cross-application recovery E2E was run. No real funds or production changes.

## Cashu blocked address change diagnostics (2026-10-16)

The owner confirms unchanged saves work after the previous fix, but changing the
payout address remains blocked while the visible list contains two settled and one
expired unpaid row. The owner identifies CashuMelt as 1.4.0.3; its exact deployed
commit and safety counters have not been inspected. Previous final Satflux commit
`6b289f751b535cb09a061e6f4d94c466e1425417` passed
[CI 38809977505](https://github.com/webiumsk/satflux/actions/runs/38809977505),
independently verified by QA: 1,656 PHP tests/7,025 assertions, 726 Vitest tests,
20 E2E passes/three intentional skips, Pint/PHPStan/typecheck/locales/build/audits.
Fresh CI installed HttpFoundation 7.4.21. PR #394 was open at that head.

Independent backend/frontend/security diagnosis confirms a Satflux visibility gap:
provider safety counts are discarded, separate change records are not in the payment
list, and normalized displayed `SETTLED` can conceal original `PENDING`/missing
provider state. None establishes the actual cause or presence of funds for this
account. Terminal rows cannot override the provider's refusal.

The bounded Satflux-only follow-up contract is
[CASHU_SAFETY_DIAGNOSTICS](CASHU_SAFETY_DIAGNOSTICS.md). It adds allowlisted read-only
counts to existing responses, preserves original known provider state additively,
explains discrepancies and unavailable evidence in five locales, and prevents safety
response/error bodies from reaching client logs. Existing write checks, email grants,
no-op behavior and recovery actions remain authoritative. Implementation review and
verification use the frozen contract. Independent security and QA accept the same
14-file application/locale manifest, recorded with local test evidence in that
document. Full Pint passes 1,038 files, full PHPStan has no errors, complete frontend
tests pass 122 files/748 cases, and independent diagnostics tests pass 18/18. Actual
typecheck passes with 121 unchanged baseline diagnostics, lint has zero errors/48
existing warnings, locale parity and production build pass. Fresh published-head
CI remains the final gate; no resolved address change is claimed. The isolated PHP
test container/network are removed.

No BTCPay changes, production calls/data mutation, fund recovery, merge or deployment.
After reviewed publication and green exact-head CI, the next evidence is the owner's
two diagnostic counts and original displayed plugin state. Any provider recovery/code
change retains its separate authorization boundary; SATFLUX-005 readiness remains open.

## Cashu CI dependency follow-up (2026-10-15)

Published Cashu repair `e24608d1c03931b913936fb573e777c78c5b4b8b` passed all
application checks in
[CI 38808562407](https://github.com/webiumsk/satflux/actions/runs/38808562407):
1,656 PHP tests/7,025 assertions, Pint/PHPStan, 121 Vitest files/726 tests,
typecheck/lint/locales/build, and 20 browser tests with three intentional promotion
skips. The overall run failed at Composer audit for the previously locked
`symfony/http-foundation` 7.4.14, advisory `PKSA-xmwy-rmx5-m7h4` /
`CVE-2026-108082` (HeaderUtils Cache-Control parsing ReDoS).

The bounded follow-up changes only that package to 7.4.21 in the existing 7.4 line,
including the upstream whitespace-parsing follow-up fix. Composer generated the
partial lock update in a private manifests-only project with existing PHP/Composer,
without installing packages, running scripts/plugins, or touching application/vendor
state. The other 166 packages, root metadata, content hash and composer.json remain
unchanged. Strict validation and fresh `composer audit --locked` pass with no
advisories or abandoned packages. Lock SHA-256:
`882b46a24fbc260a0479f859aef2cf691b96964a51c0af765287c7ee85e843f4`.

Independent security/QA accept the exact lock above; exact follow-up head CI is
required before completion. Tests against the unchanged local vendor do not
validate the new lock; CI must install it and repeat the complete suite. The Cashu application manifest
below is unchanged. No audit ignore, baseline expansion, provider or production
change, merge or deployment. Temporary Cashu test containers/network are removed.

## Reviewed Cashu wallet-change repair candidate (2026-10-15)

The owner is testing the deployed but unmerged feature branch and reports missing
six-digit email confirmation when editing Cashu, plus an outstanding-settlement
error even on an unchanged save. Visible history is owner-reported as two settled
payments and one expired unpaid payment. Production and the deployed plugin build
were not inspected. Work remains Satflux only on PR
[#394](https://github.com/webiumsk/satflux/pull/394), based on
`8b2bbf6136357b8427fb82051aec932c7b7d4c87`; its prior
[CI 38596184075](https://github.com/webiumsk/satflux/actions/runs/38596184075)
passed all three jobs. That result does not validate the new repair.

The bounded design, acceptance contract, provider source evidence and rollback
constraints are in [CASHU_WALLET_CHANGE_FIX](CASHU_WALLET_CHANGE_FIX.md).
Backend/frontend implementation reuses existing email grants and store locks,
recognizes authoritative read-only no-ops, preserves unknown/outstanding safety,
captures exact grant IDs in backward-compatible encrypted journals, and distinguishes
translated safety errors in all five UI locales. Wizard-selected Cashu without a
configured wallet retains first-setup behavior. The known fallback combined-write
journal/readback limitation remains outside this bounded repair.

Independent security and QA reviewed the source. Exact repaired-head CI is required
after candidate publication; local evidence below precedes that remote run. Backend
focused safety checks pass
28 tests/147 assertions. Independent full PHP ran 1,656 cases: 1,648 passed and eight
old fixture cases failed (seven lacked grants before newly centralized enforcement;
one returned a store DTO instead of a safety DTO). Only those two fixture files were
corrected; original assertions and owner-negative cases remain. Both complete classes
then pass 32 tests/109 assertions. Exact repaired-head CI must repeat the complete PHP
suite; the earlier partial pass is not a full green result.

Full frontend validation passes: 121 files/726 Vitest tests, actual typecheck with
121 unchanged baseline diagnostics, lint with zero errors/48 existing warnings,
five-locale parity and production build. Independent QA separately
passes 26 focused cases and reruns the expanded seven-case Cashu/Lightning switch
suite, including visible translated safety errors, preserved input and no success
event after refusal. Existing English/Slovak Cashu guides are updated; missing guide
locales use the documented English fallback. Local results must not be reported as
repaired-head remote CI. Current checks remain available through PR #394.

Final independent source acceptance is by `/root/cashu_security_review` and
`/root/ci_qa_review`. The 11-file application manifest (sorted path, NUL, content,
NUL) is `141a69b0f473cbc552f79f42fb7b1c070699a5e690df08f89061305cac553251`.
Full Pint passes 1,037 files, with four subsequent type-fix files checked again;
independent full PHPStan passes with no errors. The final Cashu 28-case/147-assertion
run also passes after those type fixes. No ratchet, compiler or dependency allowance
was expanded. Graphify AST-only and orchestration/doc-link/whitespace checks pass.
Independent QA accepts candidate publication subject to exact repaired-head CI.

No new environment variable, migration, provider change, production mutation,
merge or deployment is part of this repair. After reviewed publication and passing
head CI, the next milestone remains SATFLUX-005 Satflux review/staging/readiness,
with the existing operational evidence and separate release/activation approvals.

## Validated CI repair: SATFLUX-005 (2026-10-14)

The owner requested CI diagnosis and repair on the existing Satflux feature branch.
PR [#394](https://github.com/webiumsk/satflux/pull/394) targets master at observed
head `c22660c074d7b59cc59d2d1caaa792b5d9ff4d76`.
[CI 38563933755](https://github.com/webiumsk/satflux/actions/runs/38563933755) failed
typecheck and local-first checkout; PHP/Pint/PHPStan/security checks passed.
CodeRabbit skipped review because the original PR exceeded its 100-file limit;
its success status does not mean source review occurred.

Independent diagnosis confirmed API 429 responses: earlier tests exhausted the
shared account's 120/min budget. Checkout now uses a separate synthetic ordinary
PRO account and stored authentication. Helpers negotiate JSON/check statuses and
assert the actual checkout POST before waiting for its UI link. Production rate
limits, auth/CSRF, effective access and payment fulfillment code are unchanged.

The old local typecheck wrapper could incorrectly report zero errors when child
pipe capture returned empty output after a nonzero compiler exit. That earlier
local typecheck observation is superseded. The repaired gate uses private output
files, the installed compiler directly and fails closed on crashes/silent/unparsed
errors. Six regressions include genuine new diagnostics and increased occurrences.
An isolated compile of baseline `7c4a6bc8` matched the original 100 groups/121
occurrences; current source has the same counts. Full diagnostic/source-anchor
comparison proved 32 changed prefixes are property/union display-order changes.
Only those existing fingerprints were refreshed one-to-one; no new errors or higher
counts were allowed. Independent security/QA reviewed the evidence and caps.

Local lint (zero errors/48 existing warnings), five-language parity, build, 119
Vitest files/708 tests, actual typecheck (121 existing errors, nothing new), six
runner regressions and changed-seeder Pint/syntax checks pass. Independent full E2E
passes: 20 tests, three intentional promotion-only skips, zero failures/retries,
2.5 minutes, including checkout creation and signed settlement. Independent reviewers
`/root/ci_security_review` and `/root/ci_qa_review` accept the bounded final source;
security also executed all six gate regressions. Graphify AST-only update and
orchestration/doc-link/whitespace checks pass. Published repair commit
`7b06ed7f1c1b7bda708e62887953a7a6303c6582` passed
[CI 38594038116](https://github.com/webiumsk/satflux/actions/runs/38594038116):
all three PHP, frontend and E2E jobs succeeded. Remote results are 1,631 PHP tests /
6,891 assertions, Pint 1,036 files, clean PHPStan, six gate regressions, real
121-diagnostic typecheck, 119 Vitest files/708 tests, lint/locales/build/audits and
20 E2E tests with three intentional skips and no retries. This completion record
changes documentation only; PR #394 checks track any later published head.
Tests use new disposable SQLite,
synthetic accounts/stub, cached browser/runtime, an internal-only network and a
loopback transport. No provider changes, real funds, production access, merge,
deployment or activation are authorized.
The task-specific containers, network and loopback proxy were removed/stopped after
validation. The feature worktree is clean at the repair commit; the original checkout
still contains only the preserved owner image edit. Next work is scoped Satflux PR
review/staging/readiness, subject to the separate merge/deployment/activation gates.

## Current owner constraint: Satflux only (2026-10-14)

The Product Owner clarified that BTCPay changes are not wanted, accepted the stated
limitation of Satflux-only purchase guards, and explicitly requested deletion of the
published provider feature branch. Main deleted only
`webiumsk/btcpayserver:feat/satflux-pro-sales-pause` with an exact expected-head lease
for `734791fdd8ac6df46088c43fa0152ad5f185be3e`. Remote verification confirms the
branch is absent and owned master remains
`3ec6a9847bd06e5dad348caaec373276dc718520`. No provider PR/merge/deployment or
production change occurred. The isolated local commit/worktree is retained solely
as historical evidence; do not republish, integrate or deploy it.

The published Satflux feature branch and implementation are preserved. Future
implementation/release scope is **Satflux only**, without modifying BTCPay code,
configuration or billing records. SATFLUX-004 is withdrawn from the delivery plan;
its tests and runbook below are historical evidence, not an active dependency.
Satflux guards do not establish a global ban on direct BTCPay purchases or host
prefunded AutoRenew. Do not claim those provider paths are closed. The owner accepted
this stated boundary; no further provider closure is authorized.

Next milestone remains SATFLUX-005, narrowed to Satflux PR/head CI and staging of
the approved effective access/purchase behavior. Relay cost/quota, replica/cache,
private restore and promotion-end access evidence remain outstanding. No merge,
deployment or promotion activation is authorized by this cleanup.

## Feature publication follow-up (2026-10-14)

After the accepted SATFLUX-004 result, the Product Owner explicitly requested push.
Main published the existing reviewed feature branches to their owned origins and
verified exact remote heads:

| Repository | Published branch | Verified initial publication head |
| --- | --- | --- |
| `webiumsk/satflux` | `feat/free-pro-access` | `a5ed908e384936678d4b41bdddf89c07e2eb9489` |
| `webiumsk/btcpayserver` | `feat/satflux-pro-sales-pause` | `734791fdd8ac6df46088c43fa0152ad5f185be3e` |

This publication record adds documentation only on the Satflux feature branch;
application source and reviewed provider fingerprints are unchanged. Both branches
were newly created remotely without force or tag publication. Original checkouts
and the owner's pre-existing image modification remain untouched. No upstream
write, PR creation, merge, release, deployment or production change occurred.
GitHub run queries returned no feature-branch CI runs at this observation; local
validation is not remote head CI evidence. Provider import remains locally shallow;
successful branch publication does not establish a reviewed integration/PR base.

SATFLUX-005 still needs correct release bases/PRs/head CI and the operational evidence
below. Production activation remains NO-GO and requires separate applicable approval.
The historical no-publication authorization statements below describe their original
milestone scopes and are superseded only for this explicit feature push.

## Completed locally: SATFLUX-004 (2026-10-14)

The Product Owner approved bounded provider implementation and isolated tests in
owned `webiumsk/btcpayserver` for BTCPay 2.4.5. No push, merge, deployment, production
changes or activation is authorized. Upstream, Kukks and other repositories remain
read-only. Work uses the isolated `feat/satflux-pro-sales-pause` branch based on exact
`5d0745cae6be5d8210459e38813f317673aa97b8`, imported from existing local reference
objects without cloning or changing original checkout branches. The imported
boundary is shallow; publication/base ancestry needs separate verification.

Central provider policy/services, controller/UI and disposable tests are complete.
Local provider commit: `734791fdd8ac6df46088c43fa0152ad5f185be3e`; clean feature
worktree, original owned master unchanged. Task PostgreSQL/network were torn down.
Acceptance, repository limits and the intent/settlement/reopening contract are in
[FREE_PRO_PROVIDER_IMPLEMENTATION](FREE_PRO_PROVIDER_IMPLEMENTATION.md).
The immutable pause defaults OFF, validates exact mapping, pauses every non-Enterprise
plan in the configured offering, and separates new purchase/debit from genuine paid
fulfillment. Existing settings hold durable monotonic scoped/subscriber barriers:
reopening does not perform catch-up debit; fresh deliberate purchase can reauthorize
renewal. No schema or persistence-model migration, application/package-version
change or production change was introduced. UI view models were extended.

Independent `/root/provider_pause_security` and `/root/provider_pause_qa` accept the
bounded source. Native policy 20/20, real CLI/file/environment configuration 3/3,
complete lifecycle integration 1/1 and operator documentation/help/link check 1/1
PASS. Exact native Release build passes with zero errors and three obsolete setter
warnings confined to synthetic invoice fixtures. Swagger and diff whitespace pass.
Disposable PostgreSQL/internal-only/no-chain execution covers real API/HTTP/CSRF,
balances/ledger/dates, scheduled targets, recovery, cold restart, reopening and
fresh/historical obligations. Invoice events are synthetic; full upstream regression,
real-chain regtest and provider Playwright were not executed. Frozen service SHA-256:
`c72740723fb5673bb1f52640ca3382849424c32f5cb1fb5c493482457b45498f`.

**Next milestone: SATFLUX-005 coordinated staging and release handoff.** Correct
release bases/PR head CI, deployed build/mappings/legacy inventory, all replicas,
relay costs/quotas, private restore and end-access/reopening terms remain unverified.
Production activation remains NO-GO. Publication, merge, release, deployment and
activation need their separate applicable approvals. Stock/older workers ignore
resume barriers; binary downgrade is not a safe reopening procedure.

## Readiness follow-up: SATFLUX-003 (2026-10-14)

The owner requested continuation after SATFLUX-002 and identified the deployed
BTCPay version as **2.4.5**. The main agent completed documentation-only readiness
preparation with independent payments, devops, security and QA reviews. At this
earlier milestone, no provider
implementation, remote publication, merge, deployment or production changes were
authorized or performed. Application source remains at `c0d532fb`.

Disposition: documentation handoff ready; activation **NO-GO**. See the concrete
source map, provider work proposal, staging matrix and ordered release/reversal
procedure in [FREE_PRO_READINESS](FREE_PRO_READINESS.md). The modern subscription
provider is owned BTCPay core, not the legacy Kukks plugin. The readable host
reference `3ec6a9847bd06e5dad348caaec373276dc718520` has no complete PRO-only sales
pause: independent checkout/portal/migration and prefunded AutoRenew remain outside
Satflux flags. Retired/nonrenewable or hiding links is insufficient.

Payments independently compared the exact local `v2.4.5` host reference
`5d0745cae6be5d8210459e38813f317673aa97b8`: hosted service and plan/subscriber/
checkout models match the owned reference, and the controller/store-binding changes
add no sales guard. The gap therefore applies to 2.4.5. That upstream submodule is
reference-only, not a write/publication target; actual deployed build remains unknown.

Security and QA independently hold activation. Current-artifact promotion-off can
block legacy historical reads/exports and local invoicing navigation despite data
retention. Pre-policy code rollback ignores purchase flags and can reopen PRO and
add-on sales. The runbook now states both limits; routine reversal stays on the
reviewed new artifact. Planned end/access terms remain separate owner decisions.

Operational evidence still needs exact deployed build/plan mapping, relay capacity/
write-side quota/cost enforcement, all-replica environment/cache convergence, private
artifact backup and isolated restore proof, exact release-head CI and expanded
staging. Existing scripts/static health responses cannot establish those facts.
No lower promotional quotas, subscription cancellation or permanent manual grants
were approved. Owner-reported version/subscriber/payment facts remain attributed.

Fresh GitHub read-only evidence: master is
`7bb26f3f5ccb5c3e8a1ca29350090493d3135230` (bootstrap PR #393 merged), with tree
`20c5fd77d31ebe2f3cdf3ba7707570cfce60649c`, identical to the implementation baseline.
[Master CI 38482352651](https://github.com/webiumsk/satflux/actions/runs/38482352651)
passed. No feature PR exists and no feature-head CI is claimed. The source suite
results below were not rerun for this documentation-only review; devops passed
non-mutating shell syntax checks, and QA verified unchanged application scope.

The next bounded work identified at SATFLUX-003 required repository-specific approval for a central reversible
PRO sales policy in owned `webiumsk/btcpayserver` on a selected 2.4.5-compatible
revision, with isolated tests and independent reviews. Preserve prefunded/scheduled
intent and prevent surprise catch-up spending. Conditional legacy work requires
separate authorization only if installed; merge/deployment/activation stay separate.
The owner subsequently approved and completed the provider scope in SATFLUX-004 above.

## Current milestone: SATFLUX-002 (2026-10-14)

The Product Owner approved bounded Satflux implementation after the SATFLUX-001
read-only audit. SATFLUX-002 is implemented and validated locally on
`feat/free-pro-access`, based on `7c4a6bc8731a434dc863928d616a30c823b94382`.
This is source/test evidence, not a deployed version. No push, merge, deployment,
production configuration change or external repository write is authorized or done.
The original checkout and its pre-existing `public/og-image.webp` are preserved.

Approved policy and implementation contracts are in
[FREE_PRO_IMPLEMENTATION](FREE_PRO_IMPLEMENTATION.md): non-guest plus verified
email, indefinite duration, current PRO quotas, existing manual PRO validity,
preserved over-limit data and purchased balances, and paused PRO/slot/ISDOC sales.
Effective access is separate from factual billing and excludes Enterprise/admin
privileges. Promotional access defaults off; the three purchase controls default
closed independently. Billing reconciliation and settlement remain intact.

Independent source reviewers `/root/free_pro_security` and `/root/free_pro_qa`
accepted the implementation with no unresolved blocking source findings. Review
scope was the baseline plus the final working-tree changes, not production.
Company-owner authorization, numbered WooCommerce retry completion, fresh guest/
email checks and provider-derived Enterprise credit availability were corrected
and covered by regressions. QA independently passed 55 PHP tests (282 assertions),
32 affected frontend tests and additional auth/recovery coverage. Security
inspected source and test evidence without modifying code or rerunning the suites.

| Final validation | Observed result |
| --- | --- |
| Full PHPUnit | PASS: 1,631 tests, 6,891 assertions |
| Full Vitest | PASS: 119 files, 708 tests |
| Promotion browser smoke | PASS: 4 Playwright tests, including authentication setup |
| Full Pint | PASS: 1,036 files |
| Full PHPStan | PASS: no errors; 11 obsolete ignored occurrences removed, none added |
| ESLint | PASS: 0 errors, 48 existing warnings; cap remains 52 |
| Typecheck ratchet | PASS: no new errors, baseline unchanged |
| Locale parity | PASS: EN/SK/ES/DE/CS; four existing SK-only informational keys |
| Production frontend build | PASS; existing bundle/import warnings remain |
| User documentation | 21 articles checked, including five localized access guides |
| Graphify | Existing graph queried first; isolated AST-only update, no LLM extraction |
| Diff whitespace | PASS |

All execution used synthetic accounts and disposable SQLite databases. Existing
email DNS validation passed against an internal-only fixture resolver with no
external forwarding; validation was not weakened. Browser smoke used a separate
isolated database and a loopback transport so WebCrypto retained its secure-context
requirements. No real funds, merchant credentials or production services were used.

**Next milestone: SATFLUX-003 - release and activation readiness.** Before activation,
verify a complete PRO-only purchase closure at the exact deployed BTCPay provider,
including direct checkout, credit portals and prefunded AutoRenew; Satflux-only
guards do not prove that closure. Verify hosted-relay cost enforcement, configured
plans, replica/cache propagation and reversal. Future notice/data-access terms and
paid reopening need owner decisions. No subscription cancellation or plan deletion
is an acceptable substitute. Obtain separate merge/deployment/activation approval.

## Observation baseline

Bootstrap observation date: 2026-10-13. Scope: development orchestration only.
Initial root checkout: `fix/btcpay-245-wallets` at
`f2c8536797d8ebb9d74d392bd24a73be8dd8236f`, origin `webiumsk/satflux`.
Pre-existing modified `public/og-image.webp` belongs to the Product Owner and must
remain untouched. That initial checkout was older than recent merged payment/recovery work.
Remote PR status is evidence about GitHub, not proof of a deployed server version.

Related repositories and portable path resolution are recorded in
[the registry](../../workspace/projects.yaml). Existing siblings were readable.
The plugin sibling is a stale clean checkout on `feat/sepa-nop-public-history`
at `4185325`; a newer existing isolated worktree provided current API/CI evidence.
WooCommerce is a clean `main` checkout at `061e2e83477db023ec36227dfab76f604e3818b7`.
No external repository was modified, fetched, moved or converted into a submodule.

## Current architecture and versions

See [ecosystem architecture](ECOSYSTEM_ARCHITECTURE.md) for contracts/trust boundaries.
Lockfile evidence in this checkout:

| Component | Locked version |
| --- | --- |
| Laravel / Sanctum | 12.69.3 / 4.3.3 |
| PHPUnit / Pint / Larastan | 11.5.50 / 1.29.3 / 3.10.0 |
| Vue / TypeScript / Vite | 3.5.40 / 5.9.3 / 5.4.21 |
| Pinia / Vue Router | 2.3.1 / 4.6.4 |
| Vitest / Playwright | 4.1.10 / 1.60.0 |
| @evolu/vue / @evolu/web | 1.4.0 / 2.4.0 |
| Compose PostgreSQL / Redis | 16-alpine / 7-alpine |

Source: composer.lock, package-lock.json, docker-compose.yml. Node requirement is
>=22; existing CLI Node is 22.18.0. Application package version is 2.0.0.
Recent maintained plugins use .NET SDK 10 with exact BTCPay 2.4.5 host validation.
Actual WooCommerce header version is 2.0.23; metadata compatibility is not test proof.

## Verified recent milestones

GitHub CLI read-only observations on the baseline date:

No open Satflux issues were returned by the inspected issue-list query. That does
not establish that there is no work outside GitHub issues or in other repositories.

- Satflux [#389](https://github.com/webiumsk/satflux/pull/389),
  [#390](https://github.com/webiumsk/satflux/pull/390),
  [#391](https://github.com/webiumsk/satflux/pull/391) are merged: BTCPay 2.4.5
  compatibility, wallet safety and merchant Cashu recovery.
- Satflux [#392](https://github.com/webiumsk/satflux/pull/392) is merged: readable
  Cashu store labels. This is not yet present in the inspected older root checkout.
- Webium plugins [#32](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/32)
  through [#36](https://github.com/webiumsk/BTCPayServerPluginsWebium/pull/36) are merged:
  host/client guards, settlement recovery, persisted checkout payout snapshots,
  merchant recovery API and Lightning checkout instructions. Current inspected
  CashuMelt source version is 1.4.0.3, LNAddressConnect 1.0.4.0.
- Earlier billing fulfillment/revoked-entitlement work is merged in Satflux
  [#388](https://github.com/webiumsk/satflux/pull/388).

These statuses were freshly read; historical test counts in old handoff documents
were not rerun during bootstrap. Live production settings and deployment versions
were not inspected. No new production transaction, invoice or wallet test was made.

## Development tools and testing

Codex CLI 0.162.1 supports standalone project agent TOML files, multi-agent mode,
strict configuration and app-server discovery. Ten roles and thirteen local skills
are defined. Config caps spawned threads at three, plus main. Live session capacity
is four total. Current-session routing of newly defined roles is not assumed.

Installed: Git 2.43.0, GitHub CLI 2.93.0, Docker 29.9.0, Compose 5.6.0, Node 22.18.0,
npm 11.17.0, Python, Graphify and local Chrome/Playwright dependencies. PHP/Composer
are absent from host PATH; use an existing verified isolated PHP environment. .NET
SDK 10.0.400 was verified outside default PATH; resolve it through the user's environment without
installing or tracking a machine-specific path. WP CLI is absent. Existing
`openaiDeveloperDocs` MCP is enabled; no MCP servers or plugins were installed.
System Python already includes PyYAML; default environment Python does not.

CI entry points and isolation requirements are in the registry and workflow.
Local-first relay tests, plugin exact-host suites and WooCommerce functional tests
must be selected for actual future scope. Do not run deployment scripts as checks.

## Known risks and instruction drift

1. Raw BTCPay ID prohibition in older README/architecture/Cursor material conflicts
   with the narrow existing Pay Button/API-key formatting exception in CLAUDE.md and
   StoreController. Preserve that exception; prohibit new exposure. Do not broaden it.
2. Older testing docs say Vitest is absent; package/CI show Vitest, typecheck and five
   locales. Existing rules and historical documents are preserved, not silently edited.
3. WebhookController rejects missing/invalid signature and missing secret; weaker old
   wording must not justify unsigned events. Ownership includes audited admin/support.
4. Older plugin checkout lacks current host guard/CI. Resolve the intended checkout
   before tests. The discovered BTCPay fork is not the pinned build dependency.
5. WooCommerce README/version/legacy CLAUDE instructions drift from current source.
   No comprehensive automated WordPress suite was found; plan isolated coverage.
6. PHPUnit defaults can be overridden by inherited environment/cached config; inspect
   effective disposable targets before tests. E2E traces may contain sensitive state.
7. Cashu safety depends on mint trust, original payment snapshots, Data Protection keys
   and worker-version consistency. Outstanding-payment guards may not be bypassed.
8. New project configuration may need discovery in a future client session; this
   bootstrap does not restart the active session or modify user trust/configuration.

## Historical Free-PRO bootstrap boundary

The bootstrap permitted only a subsequent read-only audit. That historical boundary
is superseded for bounded Satflux source/test implementation by the explicit owner
approval recorded above and in [DECISION_LOG](DECISION_LOG.md). Older audit-only
instructions are not release authorization. Backend, payments, frontend, designer,
security and QA reviews were completed; related repositories remained read-only.

Product Owner clarification: Satflux currently has ZERO paying subscribers. This is
owner-provided context, not an independently verified billing finding. The audit used
non-production evidence and stated its limits; it did not access or modify production
billing data. Existing paying-customer migration, refunds and discontinued-paid-access communications are
outside the intended scope unless evidence contradicts the assumption.

The owner also reports no in-flight payments. Both statements remain owner-provided
context. Billing handling is retained regardless of those assumptions. Eligibility,
duration and quotas were approved; ending/reopening and deployment details remain
separate decisions.

## Bootstrap validation record

Completed setup checks at the bootstrap working-tree scope, base revision above:

| Check | Observed result |
| --- | --- |
| `python3 scripts/orchestration/validate.py --check-projects` | PASS: ten roles, thirteen skills, seven entries, all origins and local documentation links |
| System skill-creator validator with existing system Python/PyYAML | PASS for all thirteen SKILL.md files; no dependencies installed |
| Installed CLI strict `config/read` and `skills/list` | PASS: project limit of three spawned threads and thirteen enabled project skills |
| Installed CLI `debug prompt-input` | PASS: root AGENTS.md discovered; raw prompt discarded |
| Strict unknown-field negative control | PASS: unsupported field rejected |
| Ten role session-setting probes | PASS: developer instructions and optional read-only sandbox settings accepted |
| Three synthetic RPC transport tests | PASS: fragmented/buffered frames, partial-frame timeout and RPC error handling |
| Preservation fingerprints | PASS: existing README/CLAUDE/SECURITY, Cursor/GitHub files, global configuration/auth where present and pre-existing image unchanged |

Main `/root` authored orchestration. Genuine delegated agents audited the ecosystem,
NBDP reference and security boundaries. Independent reviewers `/root/security_audit`
and `/root/reference_audit` checked the new working-tree scope using the new security
and QA role files. Initial review accepted scope/authorization/structure and identified
probe assertion/transport issues; main corrected them. Follow-up security review
accepted the revised scope with no material findings. Independent QA accepted the
setup, reran all three transport tests successfully and checked twenty immediate-exit
response cases. Neither reviewer independently reran the live CLI probe; that result
is coordinator evidence. No blocking review finding remains.
Project-resume and cross-repository-coordination skill dry runs selected read-only
audit waves and did not authorize promotional implementation.

The CLI probe needed normal tool-approved execution outside the default sandbox to
start existing CLI runtime machinery. It preserved HOME/CODEX_HOME, configuration,
credentials and trust; only child diagnostics/state used temporary paths. No model
thread was created and the active session was not restarted. Standalone role metadata
conforms to the documented schema and TOML checks; native named-role selection/hot
reload in this active client was not exercised. Do not claim that setting acceptance
alone demonstrates native routing. Actual explicit-role subagent delegation worked.

No application/plugin code, CI configuration, database, wallet, invoice or production
settings changed during bootstrap. The initial local setup performed no clone,
dependency installation, external write, push, PR, merge, publication or deployment.

## Authorized publication follow-up

The Product Owner authorized committing/pushing the orchestration files and opening
an unmerged PR only in `webiumsk/satflux`. The dedicated branch is
`chore/persistent-multi-agent-bootstrap`, based on verified default branch `master`
at `13011b707d3e13c2d26f55889c15381c357b983b`. Initial checkout observations above
remain historical evidence. The image hash was unchanged when switching branches;
the pre-existing modified image remains excluded from commit and PR scope. Re-run
validation evidence and publication results belong in the PR handoff. Sibling writes,
merging, deployments and starting the free-PRO audit are not authorized by this step.

## PR review follow-up

Bootstrap PR [#393](https://github.com/webiumsk/satflux/pull/393) is open against master.
Its initial commit `bc4c4a2ee6b6b05f8a03c3c56a0fe6d3f559522e` passed PHP, Node and
Playwright CI. CodeRabbit identified three validation issues: settings need checkout
project-layer provenance, registry paths need exact checkout-root verification and
Git metadata reads need bounded timeouts. Follow-up guards retain value checks,
reject matching global/session/other-checkout origins and nested directories, and
report a 10-second Git timeout as CLI failure. Nine synthetic tests and the installed
CLI probe pass; all helper functions have docstrings. Independent security/QA review
accepted these fixes. Updated-head CI/review is separate from the initial green run.

The Product Owner requested recurring CI/CodeRabbit checks. Local reminder capability
was tested with the installed Codex queue command into this same thread. Runtime
watcher state is temporary and private, excluded from Git. Monitoring must use fixed
read-only GitHub requests/reminders, deduplicate changes, retain managed permissions,
stop when the PR closes and have a bounded lifetime. It must not automatically edit,
publish, merge, deploy or begin the free-PRO audit. The recorded role-routing limit
and non-production-only future billing audit scope remain unchanged.

## 2026-10-19: offline security and reliability investigation

The Product Owner authorized source-level investigation and focused fixes in
Satflux and the owned CashuMelt plugin, with no production access or deployment.
The evidence, confidence limits, code references, test results and production
diagnostic runbook are recorded in
[the incident analysis](../security/incident-analysis-2026-10-18.md) and
[the read-only runbook](../operations/SECURITY_RELIABILITY_DIAGNOSTICS_2026-10.md).
Satflux has 82 focused tests/298 assertions passing; its full PHP, Node and E2E
CI jobs pass on PR #395. CashuMelt's 147/147 tests and build passed on PR #40;
the latest independent review requested a narrow upgrade-era manual-review fix,
now under re-review. Both changes are committed and published as open PRs. Neither
is merged, packaged or deployed. No production behavior is verified. Next
milestone: finish PR review and exact-head CI, then obtain redacted aggregate
diagnostics from the operator. No account, wallet, billing, payment or production
configuration action is implied.

## 2026-10-21: Tickets sales and merchant ordering implementation

The Product Owner approved the generic public sales design and authorized its
implementation together with persisted inline ticket-type ordering. Existing
Satflux event/type forms remain authoritative; public sales and hosted payment stay
on the BTCPay host. The feature contract and ownership are recorded in
[TICKETS_SALES_ORDERING](TICKETS_SALES_ORDERING.md).

Fresh GitHub evidence verifies PR #396 merged into master at
`fe0ace6eeeab4019a557326c4b05166b53e99f59`; the new consumer branch starts there.
The owned plugin branch starts at `6bdd68828673c8a75aa744f9f3483b7a251a938d`,
with the prior uncommitted Tickets 2.0.4 candidate preserved as a dependency.
Original checkouts and the owner's modified image are untouched. The feature
candidate is 2.1.0; repository publication and deployment status are separate gates.

Current local evidence: 67 related PHP tests/209 assertions, followed by 34 new
order-specific tests/91 assertions including audited admin/support access; full
PHPStan passes without expanding its baseline. Frontend specialist reports focused
31 cases, lint, five-locale parity, unchanged typecheck ratchet and build passing.
Actual rendered plugin-page QA, provider database tests and final security review
are in progress. Do not treat these interim results as final acceptance.


### Final Tickets local acceptance

Implementation and independent source/security/QA review are complete locally.
The frozen provider passes 100/100 tests, including 15 actual disposable PostgreSQL
cases; exact-host build and guard pass. Independent browser QA passes 32 actual
compiled-Razor/embedded-asset checks plus 9 visual/contrast scenarios, including
private virtual-location exclusion and stable responsive contact/summary behaviour.
The provider DLL SHA256 is
`a24b3e36f55545b82fa4894df608adc670bd09b92b913a8f1db327127240b2da`.

Final consumer verification passes 850 Vitest cases/128 files (serialized without
changing timeouts), 77 relevant PHP/documentation cases/241 assertions, PHPStan,
Pint, lint, five-locale parity, unchanged typecheck ratchet and production build.
Earlier parallel local timeouts are superseded by the unchanged complete serial
pass; quiet agent-reporter output was expected. See the implementation record for
review scope and limitations rather than inferring deployment readiness.

New branches remain local/uncommitted. Original plugin WIP and pinned core source
are unchanged; no production or remote publication was performed. Next milestone:
separate repository publication/CI handoff, provider-first staging and reviewed
release/rollback planning. No installer, merge or deployment occurred. Existing
Greenfield purchase recovery, strict inventory holds, all-zero checkout and external
email/raffle exactly-once guarantees remain outside this completed layout scope.


### Tickets delivery authorization and local installer

The Product Owner subsequently authorized a local installable Tickets 2.1.0 package
and commit/push of the Satflux consumer branch `feat/tickets-sales-ordering` to the
owned GitHub repository. This supersedes the local-only publication gate above for
these two actions. Plugin repository publication, merge, installation and deployment
remain separate gates.

The pinned BTCPay 2.4.5 PluginPacker produced the standalone 2.1.0.0 installer from
the unchanged reviewed Release DLL. Archive SHA256:
`d892b22df02ddbe5970e2faab70489d14f05be30c056ca822b5965077c0b6df3`.
The package sidecar declares `BTCPayServer >=2.4.5`; its DLL checksum matches the
accepted build above. Existing host/transitive dependency advisories remain open;
packaging does not upgrade dependencies or establish production readiness.

Consumer publication is scoped to the 23 reviewed application/test/localization/doc
files. Feature-branch pushes alone do not trigger this repository's CI workflow;
exact-head PR CI and representative provider-first staging are the next gates.
No production configuration or `.env` change is required by this milestone.


## 2026-10-22: Tickets 2.1.1 public language and local times

The Product Owner reported successful 2.1.0 operation with incorrect public UI
language/time presentation. Source and an offline browser reproduction confirm
invariant host culture forces English and the old view explicitly displays UTC.
The focused 2.1.1 candidate resolves supported browser/explicit language, preserves
drafts during a bounded presentation-only language POST, and formats UTC instants
in the visitor's labelled browser zone with an accurate UTC fallback. No event
persistence, checkout/fulfillment, core or third-party plugin change is included.

Exact-host Release build passes; 108 non-database .NET cases (including 23 new
presentation cases) and 7 production-JS cases pass with no skips. The original
15 PostgreSQL cases belong to the preceding 2.1.0 acceptance and were not rerun for
this presentation-only delta. Independent security accepts the frozen eight-file
scope. Independent QA passes 39 focused browser checks and 32 existing browser
regressions. A scoped CSS correction closes the new picker contrast finding; the
final rebuilt embedded assets pass six light/dark viewport scenarios across all
button states, 44px keyboard/label checks and draft/local-time smoke tests.

Local installer version 2.1.1.0 contains the tested DLL SHA256
`2e4d27ee3c9aab3bab750e8c5dfcfc70c73f6aaf3e260976bc6277cdd888bcc9`.
Archive SHA256 is `3c173ddf1257943744b6d7a411b2d4e516b93b26580d90bf558b7211d33293b9`.
The previous installer and original plugin WIP remain unchanged. Consumer code
remains the already pushed `a9be6a17e63f6070b4145b3509070bd8466ee900`; its new
follow-up docs are local. Plugin changes remain uncommitted/unpublished. No live
behavior, installation, merge or production deployment is independently verified.

Independent final archive QA also passes: 103 entries, exact browser-tested DLL
and assets, CRC/path/sidecar checksums and no forbidden configuration/key/database
artifacts. The checked local installer is ready for Product Owner delivery; no
plugin source publication or production installation is performed.


## 2026-10-22: Tickets 2.1.2 header switch and asset freshness

The Product Owner reports 2.1.1 URL language works but its button and time
enhancement do not. Independent offline reproduction using the actual unchanged
shared BTCPay layout, pinned CSP and production asset handlers establishes that
fresh 2.1.1 assets work, while replaying 2.1.0 JS at the identical unversioned URL
reproduces both the UTC display and ineffective language button. No missing nonce
cause is supported. Actual operator cache provenance remains unverified. Earlier
layout-override QA was narrower and did not establish this host/cache integration.

Candidate 2.1.2 moves language selection to the header and changes constant public
copy, accessible labels, amounts and local dates immediately in the existing DOM.
It sends no request and preserves recipient inputs/drafts, quantities, delivery
choice and nonce; hidden form language/shareable URL stay synchronized. Embedded
JS/CSS have content-hash paths with exact version validation/cache policy; old
paths remain compatible. UTC storage, CSP, CSRF, payment/session/recovery and
private virtual-location boundaries remain unchanged.

Exact-host build passes (zero errors, 19 existing warnings). All 115 non-database
.NET and 10 production-JS cases pass, zero skips. Independent security accepts the
frozen eight-file digest
`a82819c1b2b71127e6bd8eaa3ded0c281311571b830d9642eaee3a32a06c81d6`.
Final full-layout/browser/cache and installer QA remain the local delivery gate.
The local 2.1.2.0 archive SHA256 is
`58dabe1628d9350f87ca8f9eaa05f0e1c7c91efe6d27ead7a8f486374dbbb207`;
its exact tested DLL SHA256 is
`06e3f19ddaa3592524a2bd99496e8816e873a1f921dc187a33abcd64212e2a65`.
The prior installer/original WIP/pinned core remain unchanged. No plugin source
commit/publication, merge, production access or deployment is performed.

Final 2.1.2 delivery gate passes: independent QA accepts 26 focused actual shared-
layout/CSP/resource-route browser cases and the exact 103-entry installer. Header
switching preserves drafts without requests, new hash paths bypass the reproduced
stale legacy script, and browser-zone/DST times are correct. Synthetic-DI/page/
submit, Chrome-engine and non-production limits are detailed in the handoff.
Operator Brave/+/-/empty-console feedback is consistent; actual cache origin is
still unverified. Ready to deliver the checked local installer; publication/CI and
representative staging remain separate next steps under applicable authorization.

QA additionally passes the host-filter CSRF check (missing token 400, valid
synthetic token 200) and stops its disposable harness. All required local delivery
gates are complete; source/package fingerprints remain unchanged.

## Tickets 2.2.0 venue-time correction (2026-10-22)

Owner-approved implementation separates physical venue time from virtual visitor time.
The owner confirms all historical physical events are `Europe/Bratislava`; the additive
provider migration backfills only their null timezone metadata and preserves all UTC
instants. New authoring extends existing forms, with provider-owned IANA catalog/civil
conversion and safe partial date updates. See TICKETS_SALES_ORDERING.md for the full
contract, bounded agent ownership, provider-first rollout and rollback limitations.
Independent security/QA and exact-host tests are in progress; no production migration,
installation, deployment or merge has been performed. Graphify graph/tool remain
unavailable; targeted source reads were used. Existing unrelated image edits remain
untouched in the original checkout. The prior 2.1.2 installer remains immutable.

### Store timezone clarification

The owner approved store-default plus event-override behavior. Source review confirms
that BTCPay 2.4.5 does not persist Satflux's outbound store `timeZone`; use local
`Store.timezone` as authority. Settings reload previously defaulted to UTC instead
of reading local data, allowing a later Save to overwrite it. A focused correction
and reload/save regression now pass within 125 related PHP cases / 386 assertions.
No store records or provisioning defaults were changed. The initial 2.2.0 candidate
is preserved; final follow-up adds safe new-event defaults and native stale-schedule
rejection before a replacement local artifact/review. No release or deployment yet.

### Final 2.2.0 source/test follow-up

Store-default behavior and the local settings read fix are complete. Native stale
forms compare original start/end UTC ticks plus original venue/type before conversion
or writes, preserve drafts and require reload on conflict. Exact-host final build
passes; provider172/172 (21 PG) and consumer125/386 pass; frontend74 focused tests,
full PHPStan/Pint, final typecheck (120 unchanged) and production build pass.
Independent security accepts bounded source, including closure of both stale native
schedule reproductions; final actual-artifact QA remains the last local gate.
Final source digests and the new archive302e2065/DLL15fbf88c identity are recorded in
TICKETS_SALES_ORDERING.md. Prior candidates are preserved. No source publication,
merge, production migration or deployment has occurred in this follow-up.

### Independent final local acceptance

Independent security accepts final provider 150 / consumer 20 fingerprints. Independent
QA passes final 8 actual native guard database cases, 8 compiled browser smoke cases
and final archive/DLL/resource equality, in addition to baseline 31 browser / 5 real
upgrade groups and focused frontend checks. No remaining blocker in authorized scope.
Local installer 2.2.0.0 final archive SHA 302e206578c213b4740637df34ce8f4f5a3d96503f901e5531171251eedf82b3
contains tested DLL 15fbf88ce04543226540be0fe1347d8ece46cf871223eaf7a50702856c726c0c.
Next milestone is separately coordinated provider installation before the Satflux
consumer release and deployed acceptance. Nothing was merged or deployed. Current
follow-up source changes are local; the previously pushed branch head is unchanged.

### Owner-authorized Satflux publication

The Product Owner authorizes pushing the prepared Satflux follow-up to the existing
`feat/tickets-sales-ordering` branch and PR #397. Remote head before publication is
`a9be6a17e63f6070b4145b3509070bd8466ee900`; it matches the local parent. The reviewed
20-file consumer fingerprint remains
`0efce0679cc1e1b9269c7b993c8be2cc8ba0b5476eff835c1a3815f3303c124e`.
Only publication documentation is added after final independent review. Previous
local test results remain evidence for unchanged application source, not new CI
results. Exact published-head CI and GitHub review are the next source gates; install
provider 2.2.0 before consumer deployment. This authorizes no plugin publication,
merge, production migration or deployment. The unrelated original image edit remains
excluded from this branch and commit.

### PR #397 CodeRabbit follow-up

At published head `e8333505`, GitHub CI run 39694269173 passes PHP, Node and Playwright;
CodeRabbit reviews the same head and reports four minor comments. Three concern prose
and acceptance-label spacing. One confirmed error-classification defect maps unrelated
BTCPay write failures to timezone upgrade/time errors whenever a new time field is sent.

Separate catalog validation from event writes in both create and update. Map write
errors only when new time fields are present and the provider code exactly matches the
shared allowlisted event-time field map; otherwise preserve the existing exception path.
Catalog failures still block writes and return safe capability errors. Legacy UTC-only
behavior, tenant/merchant-key checks and provider/frontend source remain unchanged.

New tests reproduce 10 failures on the old source. The corrected related PHP scope
passes 152 tests / 513 assertions; full PHPStan and changed-file Pint pass. Production
error-response tests use debug=false and confirm no upstream private message is returned.
This does not claim production log redaction or live verification. Independent review
and fresh published-head CI remain the delivery gates for this bounded follow-up.

Independent follow-up QA passes 65 focused cases / 229 assertions; security accepts
code/test digest a58189fb91208e0d63b47e022ad88a47fd00f4ce8b635896a12b9453b88ea978.
All four CodeRabbit comments are addressed. Its initial docstring-coverage advisory
remains distinct from the successful GitHub checks; do not add boilerplate or weaken
configuration to suppress it. Publish the reviewed bounded fix to the existing PR
under the owner's authorization, then verify fresh-head CI. No plugin repack, merge,
production access or deployment is part of this follow-up.

## 2026-10-24: PR #400 CI and review remediation

The Product Owner authorized fixing the CI and CodeRabbit findings in
[PR #400](https://github.com/webiumsk/satflux/pull/400). PR #399 is now merged;
master `709befa2` was merged into the existing feature branch as `bc7a7240` before
implementation. The original checkout and its pre-existing image edit are preserved.
No external repository, billing policy or deployment configuration changed.

The three CI failures were missing deterministic LUD-21 discovery in the BTCPay
compatibility fixture, which now reaches its original invoice/recovery assertions.
Cashu settings 404 can use the configured default only after fresh strict safety
verification. Successful presentation invoice scans have a ten-second scoped cache;
final writes still check freshly. Chorala reschedules early/clock-shifted callbacks.
Edited pending saves show localized retry guidance without stale recovery or
cross-store notices.

Independent reviewers `/root/review400_independent_security` and
`/root/review400_independent_qa` accepted the bounded working-tree remediation
against `bc7a7240`, with no blocking findings. QA independently ran 40 focused
frontend cases and the final 23-case wallet subset; locale parity passed. Coordinator
validation passed all 959 Vitest cases in 136 files, 86 unique selected PHP cases
across the initial run and corrected-fixture rerun, PHPStan, build, locale parity and
typecheck gate tests. Pint passed all 1,054 files. Typecheck retains 120 baselined errors; ESLint retains 48
warnings with no new errors. Shared local DOMPurify is 3.4.16 while the preserved
lockfile specifies 3.4.17; exact locked dependency validation remains GitHub CI.
The full offline PHP suite was not rerun because of the recorded DNS restriction.
Fresh remote CI and CodeRabbit status must be read for the newly published revision.
Merge, staging acceptance and deployment remain separate approval/verification gates.

## 2026-10-27: protected API reference implemented

The owner authorized a new branch and implementation. `feat/api-documentation`
starts at merged master `7252d531`; unrelated original-checkout edits are preserved.
Only Satflux changed. `/admin/api-docs` uses bundled Scalar with authenticated
admin/support catalog/spec endpoints and three reviewed static OpenAPI
documents. Fourteen operations cover representative account/store/raffle APIs,
seven WooCommerce connect/integration operations and two inbound webhooks. The
449-entry API inventory explicitly defers 435 operations; this is initial coverage.
See [reference](../API_REFERENCE.md) and [implementation](API_DOCUMENTATION_IMPLEMENTATION.md).

The backend reuses `RequireVerifiedEmail`, including its existing exception for
an unverified admin/support account (`User::isSupport()`) using a Sanctum PAT
named `btcpay-config-bot`.
Ordinary unverified staff sessions remain denied. Existing guest handling is
unchanged; the staff role check still applies. No stricter verification policy
is introduced by this portal.

Scramble 0.13.49 is build-time only; default runtime routes are disabled. CI checks
isolated deterministic generation, route/source drift and OpenAPI validity.
Cookie/CSRF, scoped bearer, connect-code and distinct bank/BTCPay authentication
contracts remain separate. Browser interaction, AI/MCP uploads, telemetry and
external schema transport are disabled. No business authorization, billing,
subscription, provider repository or deployment behavior was changed.

Independent QA and security accept the 31-file functional/artifact digest
`88db19b214786e511fbb1ffbc8354036c53b0ba81b3689d158e7bb7be7f8f08a`.
QA found and verified fixes for MCP schema upload and Mailgun field aliases.
Eight focused PHP tests / 170 assertions, 17 portal tests, eight validator cases,
all three schemas and nine synthetic Chrome scenarios pass. Full frontend run
passes 1,000 cases / 138 files before scoped dependency follow-ups; affected cases
pass again on final pins. Final build, PHPStan, Pint, locales and Composer
validation/audit pass. ESLint retains 48 warnings; typecheck retains 120 baseline
errors. Existing 2,009-case PHP run has six email-DNS failures, all independently
reproduced on the clean base in the same network-disabled environment.

New dependency advisories are patched. Production npm audit matches base counts
(35 moderate / six high / zero critical); existing advisories remain separate
maintenance work. The private renderer chunk is about 821 KB gzip and loads with
its route. Browser QA uses synthetic transport/authentication, not deployed state.
Private milestone evidence is retained under `api-documentation-2026-10-27`.
Next is owner-authorized publication and exact-head remote CI/review. No agent
push, merge or deployment occurred; deferred BTCPay 2.4.6 work remains deferred.

## 2026-10-27: PR #405 clean-install and review follow-up

The owner authorizes fixing and pushing the existing PR. At published head
`4c14d216`, PHP CI passes 2,017 cases / 9,369 assertions and changelog passes;
Node/E2E fail at `npm ci`, before application tests. The failure is reproduced
with exact CI npm 10.9.9. Move the SDK utility security override to project scope
and restore 27 missing dev/optional esbuild lock entries. No existing locked
versions change. Normal pristine npm 10 and 11 installs now pass; independent QA
repeats npm 10 installation with an empty task cache. No global tool configuration,
CI bypass, legacy-peer flag, audit threshold or provider repository changes occur.

Four CodeRabbit findings are corrected: consistent zero-limit meaning, production
JSON plus matching assets, inherited admin/support bot-PAT email-verification
exception, and auto-issued inbox-only PDF scope. Real PAT tests cover both staff
roles, wrong token names and nonstaff denial. Runtime auth behavior is unchanged.

Independent QA/security accept 34-file digest
`dc86ea040cfd7f3ffdb941670de9394f7d14e43ffa174ca15648bb13f9beff1c`.
Fresh dependencies pass 1,000 frontend tests / 138 files, build, lint (48 inherited
warnings), typecheck (120 baseline errors), locales, 17 portal cases, eight validator
cases and all three schemas. Eleven PHP cases / 230 assertions, extra independent
PAT probes (3 / 45), export and scoped Pint pass. Runtime npm audit remains
35 moderate / six high / zero low or critical; no new advisory is introduced.
Local Node 22.18 differs from CI's 22.23.3 patch; npm is the exact 10.9.9 version.
Next publish the accepted follow-up and verify fresh-head CI/review. No merge or
deployment approval is inferred. CodeRabbit's prior docstring advisory remains
separate from its successful GitHub check; no boilerplate or gate weakening is used.


## 2026-10-28 - Support tables and chronological messages

Owner confirms PR406 merged; verified latest master d3cb91ba is the base of
`fix/support-admin-message-usability`. Support connections now have confined,
keyboard-accessible horizontal scrolling, compact pills and mobile text wrapping.
Admin creation/login and message timestamps share DD.MM.YYYY HH:mm browser-local
time. The mixed message feed is chronological, with category/unread filters,
visible read labels, accessible actions and owned local-UUID invoice destinations
that recognize actual BTCPay invoicestate paths. Encrypted cursor continuation
bounds upstream scans without cutting off older matches; cursor expiry refreshes
the first page. The security banner and red alert labels remain.

Independent QA and security accept the 18-file source digest `5c2bd32c`. QA found and
reverified three fixes: mobile support overflow, long critical-message overflow
and expired-cursor retry. Targeted backend 26 tests/191 assertions; final component 22;
independent traversal 1/1242 and security 3/43 pass. Full frontend suite 1024/140 files
passed before the final recovery followup; final targeted 22 and independent
recovery browser checks cover that followup. Final build, typecheck (120 inherited
baseline errors, none new), lint (48 inherited warnings), locale parity, API export,
Pint 1076 and PHPStan pass. Three obsolete PHPStan baseline entries (five occurrences)
were removed; no ratchet was increased. Existing EN/SK security docs and five UI
locales updated, with inherited per-article fallback retained.

Local port 8080 still mounts the preserved dirty bootstrap checkout, not this feature
worktree. Its configured external provider and existing database/auth were not used
for QA. Browser checks use synthetic components; PHP uses disposable SQLite and
fake HTTP. No full-stack auth E2E, production access, push, merge or deployment.
Upstream offset pagination can shift if notifications arrive during traversal;
refresh starts anew. Local code is ready for publication on owner request; add its
canonical PR-linked changelog fragment when the PR number is known.


## 2026-10-28 - Usability publication

Owner authorizes publication. Reviewed implementation `51a929de` is pushed to
`fix/support-admin-message-usability` and PR407 is open against master. The
publication followup adds the canonical PR407 changelog fragment for Chorala;
no runtime implementation hashes change. Verify fresh CI and CodeRabbit against
the final followup head separately from local checks. No merge, deployment or
production publication is performed. The original dirty checkout remains preserved.


## 2026-10-28 - PR407 filtered-message pagination review fix

Exact-head CI 40745248257 at `33242fd8` passed PHP 2070/9831 assertions, frontend 1027,
Playwright 20, static checks and Composer audit. CodeRabbit's successful check also
posted one valid minor finding 4404620462: trailing excluded notifications caused
has_more=true, then a terminal empty page hid Previous. Its 63.16% docstring advisory
is separate from that functional finding and the successful check.

Consume trailing nonmatches through the existing bounded peek before calculating
has_more. Keep Previous outside the list-only branch so legitimate terminal empty
scan pages remain navigable. Two PHP and two component regressions fail on the
previous head and pass with the fix. Independent QA/security accept four-file
digest `949cf289`: PHP 28/218, component 24, QA traversal/boundaries 2/1463 and three
browser scenarios pass. Security probes 4/52, concrete regressions 2/27 and focused
component 13 pass. Final build, PHPStan, unchanged typecheck ratchet 120, scoped Pint
and focused ESLint pass. No locale/dependency/auth changes or gate relaxation.

Publish the reviewed followup to PR407 and verify its new CI/review independently.
The canonical PR407 changelog fragment remains unchanged. No merge, deployment,
production access or local port 8080 runtime switch is performed.


## 2026-10-28 - PR407 MIME security-audit remediation

Failed PHP job 123046518471 at `bbae0712`/run 40753609416 passed all 2072 tests/9858
assertions, Pint 1076, PHPStan and API export. Only composer audit failed on medium
PKSA-wtxr-p26d-nn42/CVE-2026-45070 in symfony/mime 7.4.13. Node 1029 and E2E 20
passed separately. Fresh October 28 advisory-range metadata and official security
release v7.4.22 supersede stale indexed descriptions naming 7.4.12 as patched.

Use native Composer to update only symfony/mime 7.4.13 to 7.4.22. No composer.json,
other package, runtime requirement, audit ignore, CI or quality-gate change. Lock
SHA `8f3133e4` matches independently accepted QA/security scope. Fresh normal 168-package
install, strict validation, platform checks and full unfiltered audit pass with
zero advisories. Actual installed MIME version/reference matches lock. Author mail
regressions 53/343 and independentQA 38/254 pass, including actual array-mail MIME
serialization of verification codes, PDF attachments and CSV exports. Security
reproduces 33 malformed parameter names accepted by old 7.4.13, rejects all 33 with new
7.4.22 and passes 8 normal MIME scenarios. Fresh-vendor PHPStan and API export pass.

Publish the accepted lock-only update to PR407 and verify its fresh full PHP job
through the Composer audit step. The earlier tests passing do not establish that
this final job is green. Preserve original local checkout/runtime and canonical
Chorala fragment; no production mail/data access, merge or deployment.


## 2026-10-28 - Turnstile-only registration implementation

Owner authorizes Turnstile now and explicitly leaves existing accounts/address
format alone. Limit this change to fresh account verification; do not implement
the broader audit's rate-limit, origin/proxy, cleanup or admin-label proposals.
New branch `feat/registration-turnstile` starts from merged master `97091276`.

Server-side Siteverify protects fresh guest and enabled legacy email registration
before account writes, mail or BTCPay provisioning. Fixed HTTPS endpoint, one
bounded request/no redirects, strict success/action/static-host verification and
production dummy-key rejection fail closed when enabled. Existing authenticated
retries, login, signed recovery, passkeys and upgrades remain separate. Public
configuration contains only enabled, public Site Key and action.

The widget loads after the phrase-backup modal is removed. The confirmed enrollment
stays in browser memory through explicit fresh-token retries; no recovery phrase
is sent to the provider or registration API. Loader readiness/timeouts/cleanup and
late-callback guards are tested. CSP allows only the fixed CF script/frame origin
when enabled. Five UI locales, existing EN/SK signup docs with inherited article
fallback, and an activation runbook are updated. No new dependencies or migrations.

Independent QA/security accept 25-file digest `305d24d0`. QA PHP 110/829, component 22
and six synthetic browser scenarios pass; security independent 6/77 and 22 frontend
pass. Full frontend 1050/142 files, final build, PHPStan, typecheck 120-baseline with
no new errors, lint 48 inherited warnings, locale parity, Pint 1079, strict Composer
validation, zero-advisory audit and generated API checks pass. Full local PHP 2116
execution exposed one outdated public-config exact-response expectation; only that
expected additive object was updated, preserving assertExactJson. Focused 44/482
pass after the adjustment, all 24 prior source hashes remain unchanged; fresh full
CI must establish the final whole-suite result.

Real Managed-widget credentials and deployment target/configuration are not
verified. An asynchronous question requests widget readiness/public Site Key only;
secrets must stay in the protected Satflux backend environment, never chat/source.
Default disabled supports compatible rollout, not a claim of active production
protection. Deploy both client/server before setting TURNSTILE_ENABLED=true; then
verify actual public configuration. No existing-account/data/status changes or
production tests, merge/deployment, live Cloudflare calls or runtime switch.


PR408 is open from `feat/registration-turnstile`, implementation `340a8e11`, with
independent accepted 25-file `305d24d0` unchanged. Add its canonical Chorala fragment
and verify final exact-head CI/CodeRabbit. Activation remains pending actual
Managed-widget keys in the Satflux backend environment and deployed configuration;
no production protection or merge/deployment is inferred from publication.


## 2026-10-28 - PR408 integration review followup

Exact-head `7ec299a6` PHP CI passed 2116 tests/10342 assertions, Pint/PHPStan/API
checks and audit; Node 1050 passed. E2E 19 passed/1 failed because the correct 409
ownership message appeared twice (inline plus toast), making a strict locator
ambiguous. Remove only the duplicate Register toast; preserve inline error/retry
and every identity guard. Do not weaken the E2E locator. Regressions reproduce
the old behavior and pass after the fix.

CodeRabbit minor 4406666199 identifies informal German signup copy in a formal
auth section; align six values with Sie/Ihr/Ihre, preserving keys/placeholders.
Independent QA/security accept final 25-file digest `dab15e0d`. Followup 23
component tests and seven actual-SFC browser scenarios pass, including the real
global FlashMessage/Pinia integration. UI build/typecheck and locale parity pass.
All backend runtime hashes remain unchanged. Publish one combined followup and
verify fresh exact-head CI/review.

Owner supplied the PUBLIC Site Key and will place Secret Key in the protected
Satflux backend environment. Never copy the secret into chat/source or public API
config. Keep TURNSTILE_ENABLED=false until reviewed code is deployed, then enable
and verify public configuration. No activation or secret installation is claimed.


## 2026-10-28 - Turnstile async SDK incident

PR408 merged as `1e1f2d09`. The owner reports deployment and server environment setup. Read-only public checks confirm enabled registration configuration, the supplied public Site Key, action `register` and the required Cloudflare script/frame CSP origins. This verifies runtime configuration, not successful account creation or an exact deployed revision. The public health endpoint denied this diagnostic request. No production signup, Siteverify, account or wallet write was attempted.

The merged PR408 loader combines an async/defer SDK script with `turnstile.ready()`. The current public Cloudflare SDK rejects that combination with code3857 before widget rendering; permissive synthetic readiness mocks missed the incompatibility. Prepare a bounded supported SDK onload handshake fix on a separate branch, preserving post-backup loading, manual retry, timeout, fixed provider origin and backend verification. Independent real-SDK browser reproduction and QA/security acceptance are required before publication; merge and deployment remain owner actions.


PR409 publishes the isolated fix; source/test digest `a18a4557` is independently accepted by security, and QA accepts source/tests/runbook digest `be6bf93e`. The actual SDK reproduces old3857 before rendering, initializes successfully with the fixed callback and recovers when the previous failed load left a global. All provider challenge traffic was intercepted; no human challenge, server-secret/hostname decision or production account creation was tested. Independent focused regressions pass26; local lint passes with48 existing warnings, typecheck tolerates the unchanged120 baseline, locale parity and frontend build pass. Fresh CI remains a separate publication gate. The fix includes its own PR409 changelog fragment; merge/deployment require the owner.
