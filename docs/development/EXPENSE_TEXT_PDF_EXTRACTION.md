# Text PDF expense extraction

## Authorization and acceptance contract - 2026-11-17

The owner authorizes text-PDF draft extraction after the read-only SPS diagnosis.
This applies to owned Satflux only, feature branch feat/expense-text-pdf-extraction,
based on verified master2ffd3465. Existing unrelated primary work is preserved.
No OCR/image recognition, external AI provider, dependency installation, pricing,
subscription or entitlement changes are included. The existing extraction allowance
and credit accounting apply once per successful extraction; detection remains free.
ISDOC keeps priority and its existing interpretation.

Detection is additive: retain has_isdoc as the literal ISDOC boolean and add
extraction_source with isdoc, pdf_text or null. No extracted fields/raw PDF text are
returned by detection. Existing file validation, authentication, owner/company
binding, ephemeral identity prohibitions and explicit local-file processing consent
remain intact. New frontend works with old has_isdoc-only responses as before.

The extraction response remains the existing draft shape and adds source=pdf_text
for text drafts. A new orchestration service performs bounded Smalot inspection first. Any embedded
file goes exclusively to the existing ISDOC service; no failure in that branch may
downgrade to text. PDFs with zero embedded files use the same parsed text for a
review-only draft. Structured ISDOC keeps source priority and its exact mapper. Switch detection/extract endpoints in
both controllers; retain the legacy direct import endpoint's ISDOC-only behavior so
text PDF cannot bypass draft review. No new routes or persisted schema are needed.

Map only unambiguous recognized labels/candidates for supplier, valid IBAN, external
number, explicitly labeled numeric VS (preserve leading zeroes), dates, total and
currency. Missing/conflicting fields stay absent rather than guessing or clearing
manual edits. Do not substitute tax subtotals, reference dates or document numbers
for missing payment fields. Reject or leave ambiguous credit/payment state unresolved.
Text drafts always require payment review; never mark paid or automatically enable
QR. Explicit concrete payment/deposit evidence must remain a veto under existing
review rules. Do not copy raw document text into notes/logs/errors/audit metadata.

Read only local uploaded bytes with bounded size/pages/text and parser memory where
supported. No network or external process is needed. Empty/image-only PDFs remain
unsupported with an accurate message; malformed/oversized input produces a sanitized
validation error. Real private PDF is not committed or used as a fixture. Main alone
may validate it read-only with redacted field-availability/comparison booleans.

The UI keeps per-file consent, queue identity, selection/retry/removal, quota approval,
processing-persistence gates and stale-context checks. Distinguish text-PDF discovery
from embedded ISDOC, explain draft review and keep detection/extraction before Save.
Update EN/SK/CS/DE/ES copy and guides together.

| Task | Owner | Exact write responsibility | Review |
| --- | --- | --- | --- |
| Backend | Backend specialist | New extraction facade/text mapper, two expense controllers, scoped PHP tests | Main + independent security/QA |
| Frontend | Frontend specialist | Attachment hook/panel/extract modal, two expense pages, scoped JS tests | Main + independent security/QA |
| Integration/docs | Main | Contract, five locales/guides, state, test orchestration and Git publication | Independent reviewers |
| Security | Independent security | Read-only design/source/test review | No author self-approval |
| QA | Independent QA | Read-only acceptance/regression checks | Exact frozen source evidence |

Required verification: synthetic parser tests with explicit/ambiguous/negative/paid
fields; real PDF parsing from synthetic generated PDFs; both API storage modes,
ISDOC priority and compatibility, identity/auth and failed/exhausted-quota accounting;
UI text-source consent/extract/skip/retry/queue and existing expense safety regressions;
lint/typecheck ratchets, locale parity, PHP style/static analysis and production build.
Source/review/deployment evidence remains separate. Main alone publishes reviewed
changes; merge and deployment require separate applicable owner approval.

## Design-review conditions

Independent security and QA review accept the contract with these requirements:
partial text drafts preserve omitted supplier snapshots; detail extraction retains
concrete paid/deposit notes. Text source forces review=true on server and client,
even if a malformed payload says false. Detail text extraction shows a transient
preview of the exact merged payload and requires explicit Save before field writes.
Cancel keeps only the attachment. Context/owner/record/unmount invalidate preview.
ISDOC detail behavior remains unchanged.

Parser limits are10MiB,25 text pages,20k objects,200k text bytes and a16MiB decoder
safeguard with image retention/filter-error ignoring disabled. The decoder limit
is per Flate stream and is not a global memory/CPU sandbox. The legacy structured
reader retains its existing limits after embedded-file delegation.

## Verified implementation and validation

The implementation uses sparse SK/CS/EN label parsing, checksum/length-valid IBANs,
strict amount grouping and precision, four-digit calendar dates and unique slash
interpretations. Recognized vertical bank tables are aligned within the issuer
header; bank names and buyer accounts cannot become supplier instructions. Conflicts
stay absent. For multi-page conflicting totals, only one coherent invoice-header
page agreeing with the global invoice number may establish a summary total; page
order never chooses it. Paid/deposit evidence is scanned across every page.

The detail-page text review displays the frozen merged payload, including retained
supplier snapshots, and persists exactly the approved values. Company, expense,
owner, record changes or unmount cancel review, including the approval-to-continuation
microtask interval. Queued dialogs wait for that decision. Attachment-only cancellation
preserves expense fields. Text source always requires payment review in both modes.

Repeated partial text drafts track the last automatic supplier/account pair.
Changing that automatic identity clears its old account when the next draft has
none. An unidentified incoming account cannot attach beneath a retained supplier
name; matching sparse drafts preserve their coherent pair. Deliberate contact,
manual and saved snapshots, including explicit clears, remain protected.

Main validation:

- Twelve affected JavaScript suites: **442 tests pass**.
- Eight PHP scopes: **257 total cases, 244 executed successfully, 1780 assertions**;
  **13 optional PostgreSQL quota-concurrency cases skip** because their explicit
  disposable PostgreSQL opt-in is not enabled. Existing quota implementation is
  unchanged; no new PostgreSQL concurrency execution is claimed.
- All111 new/affected core parser/API/ephemeral/ISDOC cases execute successfully,
  with936 assertions, also independently verified by QA/security.
- Full lint: zero errors and45 existing warnings within the52-warning limit;
  changed frontend paths pass scoped lint with zero warnings.
- Typecheck: no new errors against the107-error baseline. Locale parity passes.
- Pint passes all7 modified/new PHP paths; PHPStan passes4 application paths.
- Production build passes with existing dependency annotation/chunk-size warnings;
  whitespace checks pass. No baseline or warning limit is raised.

Main's read-only isolated probe of the owner's private sample detects pdf_text,
retains review=true and finds all9 basic fields. Supplier/header, IBAN, external
number, VS and the coherent-header total comparisons pass. Only field-presence
and comparison booleans are retained privately; the original document, text and
values are not copied into Git or test fixtures. This validates the supplied layout,
not universal PDF recognition or automatic payment authority.

Independent security reviewer text_pdf_security accepts all28 implementation,
locale and guide paths:111 PHP tests/936 assertions,268 initial JS tests/five suites,
synthetic payment/bank-role probes, then145 Form tests on the final recipient-pair
correction. These JavaScript scopes overlap and are not summed. Independent QA
accepts the final hashes after288 JS tests/five suites and111 PHP tests/936 assertions.
Its20 native Chrome scenarios comprise15 broad flow checks before the last Form-only
correction and5 targeted recipient-binding checks afterward, with zero page errors.
Both reproduced account-binding defects are fixed; no review blocker remains.

Frozen aggregates (sorted relative path, NUL, file SHA-256, newline):

- Implementation18: 26611dbe05d08f01d3962445a758da10a6b0e2d7ac7480cc4ce931240b00ff4d
- Source23 including locales: 89d473eb2fc6c59700143ed7f4a18648759e0c08acc1475d603b15c3565bd323
- Complete28 including guides: 9789f7632b6ffea55c9d10ce2cc2febd6ab3decfd644dc6dbd08adc926957219

The primary existing Graphify graph was queried before exploration. This worktree
has no graph; none was built/installed. All PHP runs use disposable network-disabled,
read-only containers with synthetic uncached testing configuration, SQLite in memory,
array cache/session and sync queue. Native browser fixtures use synthetic intercepted
API/persistence boundaries; no production target is exercised.
