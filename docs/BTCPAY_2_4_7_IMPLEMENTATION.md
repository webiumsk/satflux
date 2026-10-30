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

## PR411 CodeRabbit follow-up

Review of published `7bb1677d` found two valid issues:

- [Sound URL policy](https://github.com/webiumsk/satflux/pull/411#discussion_r4421723385): generic URL validation still accepted FTP. Require HTTP(S), retaining nullable/empty handling and the 500-character limit. Feature tests reject unsupported schemes before any remote request.
- [Hidden monitoring control](https://github.com/webiumsk/satflux/pull/411#discussion_r4421723391): `v-show` keeps inactive controls in the shared native form. Legacy monitoring zero blocks unrelated saves; the newly bounded invoice duration has the same problem above its maximum. Disable these controls when inactive or inaccessible. Omit only invalid inactive numeric values, preserve valid/nullable values and existing permissions, and keep active numeric min/max/step validation without adding a required constraint. No loaded value is clamped or mutated.

The retained pinned-host source merges omitted values with persisted settings before validating all durations. Therefore the client fix removes browser/local-request obstruction; it cannot guarantee a successful real-host update if the host's own persisted store has invalid durations. The merchant must explicitly choose valid Payment durations. Browser/API-stub success is not live-host acceptance. The retained host checkout's Git metadata was unavailable during this follow-up; this source inspection relies on the original pin qualification and is not a fresh host-revision verification.

The bot also reported advisory documentation coverage of 33.33% against 80%, without identifying individual missing functions. Add useful normalizer/date and form hydration/submission contracts; the bot must re-evaluate its metric. Its successful check does not establish that the advisory is cleared. No review CLI or other tool is installed for this task.

Final follow-up validation: 77 PHP tests / 215 assertions across request/settings/recurrence suites; 40 frontend tests across three files; 121 native Chromium assertions against the actual shared parent form. Independent QA also reproduces the original published-base failure and passes 74 component-level native assertions. Changed PHP Pint, full PHPStan, generated API contracts, full lint (48 existing warnings), typecheck (120 baseline errors, none new), five-locale parity and production build pass. Only synthetic SQLite-in-memory/no-network PHP and synthetic loopback browser fixtures are used.

Actual independent specialists `cr411_qa` and `cr411_security` accept the final frozen source without blocking findings. The security eight-file hash-manifest digest is `646f804b1a0415db08e30a0f60c8640c05d66493babdbc9008bc5f12459917c4`. [QA report](development/artifacts/btcpay-247-review-2026-10-30/qa.md) and [security report](development/artifacts/btcpay-247-review-2026-10-30/security.md) distinguish executed checks from source review. Fresh remote CI and any incremental CodeRabbit assessment must be checked against the resulting published head, separately from the green base. No merge, package rebuild or deployment is included in this follow-up.

### Incremental review of 0e0098db

All exact-head CI jobs and the CodeRabbit check completed successfully. The bot closed both original functional findings, then posted three minor follow-ups: a placeholder command for an uncommitted QA fixture, ambiguous formatting of a reviewer agent identifier, and the browser harness's system-Chrome default. Mark the uncommitted component observations as historical and not reproducible from the repository, keep the concrete committed shared-form command, and label the old source hashes with their reviewed revision. Format the reviewer as a repository-safe agent identifier. Allow Playwright to select its installed browser by default, using `BROWSER_EXECUTABLE_PATH` only as an explicit override. Product runtime files and earlier source-review acceptance remain unchanged.

The refreshed documentation advisory is 40% against 80%; it is separate from the successful check and the three actionable comments. No coverage threshold, test expectation, installed browser or dependency is changed to suppress it. The worktree graph is absent and graph tools are not exposed in this follow-up; these are known-file edits and no graph was installed or rebuilt.

Independent reviewer `cr411_qa` accepts the portability/documentation follow-up. Mocked invocation of the actual committed launch code passes 19 option-selection assertions for unset, empty and explicit browser overrides, preserving headless/arguments and cleanup. The actual shared-parent browser harness passes all 121 assertions with an explicitly selected existing system browser. No default bundled browser was installed or launched for this local check; default option delegation was tested without an installation. Node syntax, changed-file ESLint and `git diff --check` pass. Earlier complete CI results belong to `0e0098db`; fresh checks must cover the new published revision.
