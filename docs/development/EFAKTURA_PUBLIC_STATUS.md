# Slovak eFaktura public status correction

## Authorization and purpose

On 2026-11-09 the Product Owner approved withdrawing Slovak eFaktura readiness
claims from public product copy and clarifying the existing integration's limits.
Further Slovak eFaktura development is paused pending a separate product decision.
This change does not promise a replacement provider or a delivery date.

The existing implementation documentation states that provider requests have only
been exercised through `Http::fake`; its real-provider sandbox checklist remains
incomplete. A successful credential check and UBL export do not establish complete
delivery, receiving, reconciliation or regulatory readiness.

## Acceptance criteria and boundaries

- Public landing, feature, pricing and upgrade copy no longer markets Slovak
  eFaktura delivery as a supported ready feature in EN/SK/ES/DE/CS.
- Existing ISDOC/UBL export claims remain scoped to file generation/export.
- Company settings and displayed readiness guidance explain that complete provider
  integration is unverified, provider API terms and fees are separate, and UBL
  export alone does not deliver an invoice.
- The provider URL example uses a reserved example domain, not an unverified
  production service.
- The existing documentation slug remains available with consistent status and
  limitations in all five locales. Existing implementation/runbook evidence stays
  available to maintainers.
- Credentials, saved settings, send/receive controls, eligibility, authentication,
  authorization, encryption, payment behavior and deployment configuration remain
  unchanged. No live provider, database or merchant record is accessed.
- There is no claim that this change establishes legal readiness for another
  country or company type.

## Ownership and integration contract

| Task | Owner | Writable scope | Dependencies and acceptance evidence |
| --- | --- | --- | --- |
| UI and translated copy | Frontend specialist | Five locale JSON files and assigned invoicing/landing components/constants | Existing UI patterns; locale parity, existing focused tests, lint, typecheck and build |
| User/developer guidance | Designer specialist | README, SK eFaktura implementation document and assigned user articles | Retain canonical slugs, five-language status parity and accurate limits |
| Coordination | Main agent | This record and project state/roadmap/decision/workspace records | Preserve unrelated checkout edits and integration behavior |
| Independent QA | QA specialist | Read-only review | Inspect final scope and reproduce relevant checks |
| Independent security | Security specialist | Read-only review | Confirm no new trust, secret, authorization or behavior changes |

Only the owned Satflux repository changes. No external repository, API contract,
provider account, package version, dependency or schema changes are required.
Merge and deployment remain separate approval steps.

## Validation and review

The implementation base is owned master `8dfa449b`. Eighteen source paths change:
five locale files, two warning templates, README, the technical reference, five
localized eFaktura articles, and the English/Slovak contact and company guides.
Existing comparison support values and internal translation keys are preserved;
only the visible export label and explanation change.

The reviewed source manifest is SHA256
`5635c72e4a167e408edb2fc70ffa19d5283125371428193c2f07f19d337ab0ba`.
It hashes lexically sorted lines of `SHA256(file bytes)`, two spaces, relative
source path and newline. Coordination records are excluded.

Completed checks:

- Existing focused frontend tests: 87 passed across nine files, covering eFaktura
  settings, readiness, eligibility, sendability and inbox flows, landing showcase
  rendering, and locale messages. No test or snapshot changes were needed.
- Full frontend lint: zero errors and 48 existing warnings within the unchanged
  limit of 52.
- Full typecheck: 110 existing baselined errors, no new diagnostics; the baseline
  is unchanged.
- EN/SK/ES/DE/CS locale parity: passes; four existing informational Slovak extra
  keys remain.
- Production build: passes, with existing dependency annotation and chunk-size
  warnings. No dependency or build configuration changes.
- FAQ catalog/source validator: 12 questions, five locales and 39 source
  fingerprints pass unchanged. The edited articles are outside that source set.
- Existing changelog validation: 14 fragments pass. The sandbox blocked its
  nested Git read; the same existing command passed through normal approval.
- Five-article front matter/section parity, documentation links and Git whitespace
  checks pass.

Independent security and QA reviewers accept the same 18-path manifest without
findings. QA independently passes 322 assertions and compiles 110 selected changed
or warning messages across all five locales. The warning text/background contrast
is 8.75:1. These reviews accept the source correction only; they do not certify the
existing provider integration or regulatory readiness.

The existing primary knowledge graph was queried before exploration. This isolated
worktree has no graph; no graph installation, rebuild or cross-checkout update was
performed. There are no dependency installations, backend tests, live provider
requests or production/database operations in this task.

Local source changes are not evidence of a deployment or a production content
update. Existing database-backed FAQ content is not modified. A public changelog
fragment requires the actual future PR URL; no PR or source URL is fabricated.
