# Structured expense suppliers and manual incoming credits

## Authorization and baseline

The owner authorizes an optional supplier contact and explicit supplier IBAN on
expenses, replacing payment-account extraction from Internal note. The owner also
authorizes manual incoming credit notes with negative expense totals. Work starts
from verified remote master `eee6cd01`, which includes merged PR451 and PR452.
Historical primary-checkout state still describes those PRs as open; that evidence
is superseded by GitHub metadata and the fetched master. Deployment is unverified.

## Acceptance and contracts

- Persist nullable `company_contact_id`, `supplier_name`, `supplier_iban` and
  `payment_review_required` in server storage and their camelCase Evolu equivalents.
- Contact selection is optional and company/owner scoped. Selecting a contact
  copies its IBAN first, otherwise a compatible safely extracted account, otherwise
  an empty account. Save recipient snapshots; later contact edits never redirect
  an existing payment. Preserve deliberate edits and explicit clears.
- A conflicting extracted supplier cannot silently supply another contact's
  account. Contact hydration never overwrites a saved or manually edited account.
- QR reads structured snapshots only. Legacy free-text supplier lines cannot
  authorize payment. Existing paid/cancelled/bank-conflict, EUR, exact positive
  amount, valid IBAN, recipient-name and variable-symbol guards remain.
- Structured payment-review evidence independently blocks ambiguous, partial,
  prepaid, foreign-currency and unsupported-precision imports. Selecting a contact
  cannot clear it. Legacy null derives conservative review evidence from original
  saved payment/import notes only; supplier details are never migrated from notes.
- Manual incoming credits use signed totals and a sign-inferred form choice;
  no new invoice-line model or persisted document discriminator is introduced.
  Credit-from-expense prefills a new negative expense without copying its external
  number or variable symbol and does not claim a persisted document relationship.
- Negative expenses remain excluded from outgoing-payment QR and debit matching.
  Keep bank-linked amount/currency edit locks, status and reconciliation semantics.
- Include fields in backup/share projections and legacy-server migration,
  remapping the optional contact reference. Preserve encryption and tenant checks.

## Ownership and dependencies

| Task | Owner | Writable scope | Dependency |
| --- | --- | --- | --- |
| Backend | backend specialist | Expense model/request/controller/service, XML/spreadsheet importers, payment-review helper, additive migration, server migration exporter and scoped PHP tests | Shared field contract |
| UI | frontend specialist | Expense form/show/list/sidebar/table, supplier-contact loader, five locales and assigned component tests | Shared DTO and persistence |
| Integration | coordinator | Evolu schema/projections/maps/CRUD/imports, QR eligibility, DTO types, associated tests, guides and state | Backend/UI contract |
| Security | independent security specialist | Read-only review | Integrated frozen scope |
| QA | independent QA specialist | Read-only review and disposable synthetic checks | Integrated frozen scope |

All changes belong to Satflux only. No external repository writes, payment-provider
operations, package installation, publication, merge or deployment are included.
Unrelated primary-checkout work remains preserved in its original branch.

## Review and validation

Independent security and QA reviewed the design before implementation. The
security finding that a contact account could bypass withheld XML payment data
is addressed by the independent structured payment-review blocker above.

Validate contact isolation, precedence, clearing, manual overrides, extraction
replacement, structured-only QR, imported safety flags, duplication, signed-credit
roundtrip, positive-only bank matching and backup/migration roundtrip. Run focused
Vitest/PHP suites, existing lint/typecheck ratchets, five-locale parity and build.
PHP checks use network-disabled disposable containers, read-only code/dependencies,
tmpfs runtime/config cache and verified SQLite `:memory:`. Browser checks use
synthetic data and block external requests. Final independently reviewed results are recorded below.

Graphify was queried in the primary checkout but returned unrelated legacy edges;
the isolated worktree has no graph. No graph was installed or rebuilt.

## Final reviewed implementation

Independent security and QA accept the final 49 source/test/locale paths against
`eee6cd01`, aggregate
`bb7a48f142f9d4ec6ba8906462c729403ccafb758c8bfd2d1e1ca32d4b064c0f`.
All reported High/Medium implementation findings are fixed: legacy note-autosave
safety capture, absent extraction proof, protected recipient identity/account
snapshots and fresh contact validation during duplication.

- Main PHP: 285 tests / 1,806 assertions. Independent QA: 91 / 476.
- Main JavaScript: 837/838 in 46 affected suites; the existing exact 10 MiB archive
  case exceeded its unchanged 20-second limit under concurrent load. Its complete
  single-worker suite passes 35 tests. Archive implementation and test are unchanged.
- Independent QA JavaScript: 289 tests / 13 suites; security: 146 / four suites.
  These scopes overlap and are not summed.
- Independent Chrome: 11 desktop/320px scenarios, exact PayMe bitmap decoding,
  contact/extraction/clear transitions, credit entry, missing proof and identity
  conflict recovery. Zero external requests, page errors or payment events.
- Typecheck: no new errors, 107 inherited tolerated; no baseline increased.
  Final scoped ESLint passes without warnings. Pint checks 16 PHP files;
  scoped PHPStan, five-locale parity and whitespace checks pass. Final production
  build passes on the accepted source (1m 53s). One earlier build was interrupted
  with exit 143; the unchanged isolated retry passes. Existing large-chunk notices
  remain informational.

Explicitly selecting a contact with its own IBAN makes that contact authoritative
for the recipient name/account, even if later extraction uses different name text.
An extracted account cannot silently be paired with a different contact name.
Independent XML financial blockers still veto payment preparation.

Legacy XML rows without retained financial proof stay blocked; editing notes or
selecting contacts cannot clear the blocker. No automated account backfill from
legacy prose occurs. All clients should update together; old clients still read
legacy note fields. Deploy the additive database migration before the new client.
Received credits do not introduce refund matching or persisted original-expense
relationships. No real bank transfer or production deployment is tested.

The implementation remains uncommitted and unpublished in its durable worktree.
Private hash manifests and independent review/browser reports are retained in
`storage/app/private/reviews/expense-supplier-credit-notes/`. A publication
changelog fragment awaits an actual authorized PR URL; no placeholder source URL
or public announcement is created for this local implementation.

## Source publication - 2026-11-17

The owner requested push. Implementation commit
`1ee599001c5edcb4b2431366a9e8223a806e5b90` is published on
[`feat/expense-supplier-credit-notes`](https://github.com/webiumsk/satflux/tree/feat/expense-supplier-credit-notes)
in `webiumsk/satflux`. Direct remote Git confirms the same SHA. Its 59-path scope
contains exactly the accepted 49-source freeze and ten accompanying documents.
The follow-up commit changes five development documents only; source and
validation aggregates remain unchanged. Feature-branch push alone does not run
the repository's PR/master CI. No new CI, PR review or deployment pass is claimed.
No PR, merge, release or deployment is performed. The previous uncommitted/local
statements above describe the earlier acceptance checkpoint.

## PR454 correction policy - 2026-11-17

The owner requests all six verified review corrections. The previous explicit
contact-authority exception above is superseded: supplier identity conflicts now
require payment review in either selection/extraction order and saved-detail
re-extraction. Contact account precedence still controls the stored destination.
Nullish extraction preserves a saved VS, but an unconfirmed retained reference
cannot enable QR. Missing/deleted/inactive contact links may be detached during
copying while snapshots and review evidence remain. Live foreign contacts and
stale source owners still reject. Signed duplicate detection works in both
storage modes while bank matching remains positive-only. See
[review corrections and validation](PR454_REVIEW_FIXES.md).
