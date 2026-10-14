# Project state

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
