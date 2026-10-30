# Independent QA: integrated SEPA isolation on BTCPay 2.4.7

Reviewer: `sepa247_qa`, independent of plugin/backend implementation authors. Source and Git are read-only. Independent source, consumer and provider runtime review is complete; final archive inspection remains pending.

## Scope and provenance

Provider base `ed250c2e75d1287ac75ec163506a1fbdca1fbaf7` plus the prepared `8afed1a` merge and frozen positive-credit guard. Consumer base `4ff5c32ab2c4d9d5e7d786498c37515cd4abb439` plus prepared `885168f2`, the new retry regression for HTTP 503, five-locale hints and SEPA articles. The [QA source manifest](qa-reviewed-source.json) captures 22 provider and 28 consumer changed files before the coordinator's publication/documentation changes. This includes the two prepared SEPA plan/runbook documents in addition to the 22 provider and 26 consumer files covered by the separate [security source manifest](reviewed-source.json).

## Executed independent checks

- `php artisan test tests/Feature/SepaBmailTest.php tests/Unit/SepaBankEvidenceTest.php tests/Unit/TatraBankEmailParserTest.php` inside the coordinator-provided disposable Docker wrapper (the wrapper is not a committed repository entry point): 62 tests / 149 assertions passed, 12.03 seconds. Before execution, checked wrapper's network-none SQLite-memory/synthetic configuration and absence of cached app config. No production environment read.
- Production source equivalence: all 11 consumer PHP files independently equal `git show 885168f2:<path>`; no unreviewed functional adaptation was hidden in the host integration.
- `node scripts/check-locale-parity.mjs`: pass, only four existing informational Slovak extras. All five JSON locale files parse and all five SEPA articles exist. Reviewed EN/CS article and five setup hints for bounded classic-Tatra automatic support versus manual compact/SLSP handling.
- Provider `bash scripts/verify-btcpay-host.sh` and `bash scripts/tests/verify-btcpay-host.sh`: pass. Independently resolved exact host `2868deedd9d911b715fec7306ea9cada36d86bff`.
- XML/dependency/hash checks: version `0.8.2.0`, EF Design `10.0.14`, host minimum `>=2.4.7`, frozen matcher `ed21c325597b06127eb964a4aee68ca9580f596778ae8d0d8d8fa6b4aad52adc`, frozen isolation tests `5ef2eb1ab6b0d2ad71f63d98f646b8c1e3cbe5f878a92f1bfbeb9c1119091c30` verified.
- `git diff --check` passes for both source worktrees.

## Reviewed acceptance

Consumer tests cover unsupported old contract, unverified native origin, missing delivery secret, Mailgun delivery/MIME-header spoofing, ambiguous/incomplete evidence, actual bank header/account/amount versus payer text, and stable payload/dedup identity after provider HTTP 503. Existing queue exceptions propagate into bounded retry.

Provider review confirms store-scoped lookup and recorder ownership recheck, immutable invoice account binding, bank-origin/account proof, NOP hash against original account, pre-recorder dedup reservation, corrected-proof reuse without consumed identity, concurrent matching tests and same-reference recovery after external-record/commit interruption. The new guard is automatic-only: nonpositive credits are rejected before dedup/recorder even with high tolerance; explicit manual zero-due behavior remains tested. Existing anonymous checkout confirmation remains a merchant opt-in default-off manual path, not verified bank evidence.

CI remains pinned to 2.4.7 and provisions an explicitly synthetic SEPA database, matching the destructive fixture's localhost/database/user guard. No test silently falls back to a developer database.

## Consumer/provider compatibility matrix

| Pairing / failure | Verified behavior and evidence |
| --- | --- |
| New consumer + old plugin | Consumer feature tests pass: missing/old capability yields review, no automatic POST. |
| Old consumer + new plugin | Source trace: old reports default bank-origin false/missing account and enter review before dedup/recorder. Provider runtime suite passes all 165 cases, including legacy/unverified evidence rejection. |
| New consumer + new plugin | Consumer mocked wire contract and source trace agree on actual receiving account, positive amount, currency, reference and verified origin. Real installed-host HTTP remains main-owned. |
| Provider unavailable during report | Executed503 regression: exception propagates; retry preserves the exact payload and 64-character dedup identity. |
| Unsupported/ambiguous bank mail | Executed feature/parser negatives: no settlement report for insufficient evidence, old contract or spoofed origin. |
| New plugin + old BTCPay host | Source minimum is>=2.4.7; no 2.4.5 binary compatibility claimed. Exact source pin independently verified. |

## Provider runtime evidence

During the coordinator's exclusive database window, independently executed:

`dotnet test Plugins/BTCPayServer.Plugins.SepaInstantQr.Tests/BTCPayServer.Plugins.SepaInstantQr.Tests.csproj -c Release --no-build --no-restore -m:1` with TRX output in the verified disposable SDK 10.0.403/runtime 10.0.14 runner sharing only its newly created PostgreSQL 17 namespace. No host database port was exposed. The fixture itself verifies actual database/user identity before destructive reset.

Result: 165 passed, 0 failed, 0 skipped, 16 seconds, exit 0. Independently parsed TRX counters and confirmed all three new cases passed: nonpositive NOP amounts 0 and -1 with high tolerance, corrected same-event positive retry, and explicit manual zero-due preservation. No source writes or parallel .NET build occurred during this QA window. Released the window before main's complete provider-suite run.

A dependency-free read-only PE metadata inspector in the reviewer's disposable workspace verifies the compiled SEPA assembly name and version 0.8.2.0. It restored no external packages (package sources cleared); no tool/browser was installed. This is compiled-source evidence, not yet the final archive.

All 50 previously captured changed-file hashes remain equal after independent runtime execution. Full provider-suite and actual installed-host/package evidence remain separately coordinator-owned.

## Findings and disposition

No blocking finding identified. Accepted source and integrated consumer/provider runtime behavior within the stated scope. Final installable archive identity remains pending; these checks alone do not establish production installation, live bank semantics or rollout success.

## Boundaries and limitations

Primary Graphify query completed and returned relevant runbook nodes plus unrelated heuristics; target worktrees have no graph. Used targeted source reads; no graph install/rebuild. No dependency/browser installation, source mutation, Git publication, container restart, production database, merchant token, wallet, bank traffic or real funds. Main owns shared .NET compilation and provider database execution scheduling; this reviewer ran only the explicitly granted exclusive 165-test window. Original cross-database recording remains non-atomic; reference-based retry/reconciliation and matched database/Data Protection backups remain required. Remote CI/PR status is main-owned and must be attributed to final head.
