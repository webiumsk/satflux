# BTCPay 2.4.7 compatibility implementation

Status: Satflux implementation accepted locally for source publication, 2026-10-30. Companion provider packaging is qualified separately.

## Authorization and source boundaries

The Product Owner approved proceeding with the audited compatibility plan and explicitly approved Webium plugin repository changes (host pin/CI, Tickets/Raffle, redirects and tests). Merge, package publication and deployment remain separate approvals. No BTCPay core or third-party provider modification is required.

Satflux branch `fix/btcpay-247-compatibility` starts at master `2773f51b`; the independent SEPA checkout and unrelated local edits are preserved. Webium's same-named branch starts at `25fec3a`. Its exact host is `2868deedd9d911b715fec7306ea9cada36d86bff` (BTCPay 2.4.7). The existing SEPA isolation repair `8afed1a` is a separate prerequisite, not silently included in this compatibility branch.

## Contracts and ownership

| Area | Owner | Acceptance |
| --- | --- | --- |
| Store settings, Tickets redirect and recurrence server validation | Backend specialist | Invoice/display 60..2073600 seconds, monitoring 600..2073600; nullable/omitted preserved; HTTP(S) CSS/redirects; recurrence integer/calendar validation on create/update, Week translated to Day times seven, sparse changes retain saved state |
| Settings and Pay Button UI, localized errors and documentation | Frontend specialist | UI minute bounds without double conversion, truthful buyer-email semantics, invalid redirect blocks generation/copy, integer recurrence input, all five locales/docs |
| Provider host/dependencies/content/redirects | Plugin specialist | Exact host guard and CI, honest 2.4.7 minimum, matching versions, bounded rich text at write/editor/public/admin boundaries, old descriptions safe without destructive rewrite, redirects checked before new orders |
| Test environments, integration contracts and shared state | Coordinator | Disposable SQLite/PostgreSQL, exact lockfiles, no production wallets/funds/merchant tokens, actual host HTTP contracts, explicit qualification gaps |
| Independent security and QA | Separate reviewers | Final source scope/digests, meaningful negative/recovery evidence, findings fixed and retested |

## Change behavior

Store setting wire values remain seconds and the form remains minutes. Per-invoice probe monitoring is a different contract and is unchanged. CSS length limits and blank/null behavior are retained. Existing unsafe merchant values are not bulk rewritten.

Pay Button generates `buyerEmail` as buyer identity; merchant delivery depends on configured email rules. The unsupported notification checkbox is removed. HTTP(S) redirects retain query parameters and HTML attribute escaping. Customer navigation is not payment evidence.

Crowdfund intervals retain positive integer values instead of collapsing to a boolean. Weekly intervals map to canonical Day values with overflow checks. Active recurrence needs an explicit valid start date and supported calendar range. Create and update share the same normalizer. Neither omitted recurrence fields nor unrelated partial edits disable an existing campaign.

Tickets/Raffle use the host's bounded merchant-rich policy where formatting is intended. Raffle old stored descriptions are sanitized at public/admin read boundaries as well as on writes; native editor content is sanitized before initialization. Forms, scripts, data attributes and style blocks remain restricted. Trusted versus sandboxed iframe behavior follows the host policy.

## Validation and remaining gates

The final Satflux source passes 2,193 PHP tests / 10,571 assertions and 1,080 frontend tests. Full Pint (1,083 files), PHPStan, API contract export, locale parity, production build, lint and typecheck gates pass. Eight obsolete PHPStan suppressions were removed; no ratchet was raised. Existing lint warnings, 120 baselined typecheck errors, build warnings and one PHPUnit metadata deprecation remain attributed.

Independent QA adds 46 PHP assertions and 11 UI cases covering boundary, sparse-update, overflow, unsafe URL and escaping cases. Separate security review accepts the scoped source without blocking findings. Reviewed code/config/test manifest SHA-256: `29b1b6e6ceb4b2b8ff55eab56e6e0b8ede7cf53bf8884e17df127a183b524117`; its provider portion predates the separately reviewed legacy-width follow-up. No production core source changed after the core review.

PHP tests use only synthetic configuration, exact Composer lock versions, SQLite in memory and an internal DNS fixture with no internet route. Initial harness runs lacked the configured Dompdf driver or could not resolve fixture email DNS; those failures were not relabelled passes. The authoritative complete run passes all cases. Product email/DNS validation was not weakened.

Build/mock success does not establish installed-production-package compatibility, real settlement, migration locking on production-scale data or successful deployment. Companion Webium source, eight package identities, patched-runtime migration/restore and native browser results belong to its separate compatibility report and artifact inventory.

Provider-first rollout, exact immutable package identity, core/plugin database plus Data Protection-key backups, pending invoice/payout evidence and matched restore remain required. No mixed workers, broad merchant privileges, webhook rotation, subscription rewrite or unknown-outcome resend is introduced. Existing Boltz/SamRock setup pause and independent WooCommerce remediation remain in force.

## Publication and operational boundaries

The owner explicitly requested publication of the Satflux compatibility branch and local installable plugin archives. This authorizes neither merging the branch nor installing packages in production. Keep the separate SEPA isolation repair as a prerequisite; the compatibility-only SEPA candidate must not be represented as that repair.

Raffle startup's inherited EF history/fallback mismatch is distinct from host compatibility. Its legacy 2000/50-character description/invoice columns require the narrowly reviewed width alignment in the provider before those longer supported values can be written. Do not stamp old migration history or suppress diagnostics. Inspect actual target schema and preserve data/key backups before promotion. The core account-restriction migration's SQL-NULL StoreBlob caveat also remains an operator inventory gate.
