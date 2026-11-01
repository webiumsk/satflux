# Independent incomplete-credit evidence security review

Reviewer: `/root/bank_mime_diagnostic_security`, independent of backend implementation. Date: 2026-11-01. Repository: `webiumsk/satflux`; worktree branch `fix/tatra-ascii-wire-profile`; baseline `12f83c11f326eb3d5c658ab8c18262b149f9db8d` plus the four frozen files below. **Source security ACCEPTED: no unresolved source security finding.** This is not deployment, capture, financial replay or payment approval. Main owns publication and remaining QA/CI gates.

Root state/instructions and security role/skill were read earlier in this session and the current state was refreshed. Worktree graph absent; main's Graphify query is stale for parser/capture edges. Targeted assigned-file reads only, no rebuild/install. No production command, actual bank email, merchant values, new transfer or payment-state operation. Reviewer writes only private `/tmp` reports/probes; no repository/Git/coordination changes.

## What the reported audit establishes

The supplied audit at 2026-11-01 20:15:06 UTC has reason incomplete_credit_evidence. At baseline `SepaEmailConfirmationService.php:34-57`, this means at least one parsed Credit-direction row, the existing origin/sender gate passed, and zero credit rows satisfy all five eligibility predicates. This returns before owner API-key access, provider settings lookup or payment reporting (`:60-88`). This attempt did not reach BTCPay reporting. It does not prove positive credit amount, correct receiving account, trusted reference, supported bank layout, selected body profile or settlement.

If independently correlated to confirmed MIME intake on the reviewed deployed source, reaching this service means the MIME verifier returned a verified, sender/hash-bound payload (`BankMimeVerificationService.php:146-178`). The audit alone does not establish route/deployed revision or distinguish the signed-MIME and ASCII paths. Missing verifier_reason is expected: the SEPA review helper logs reason only (`SepaEmailConfirmationService.php:157-165`), while optional verifier_reason belongs to the separate MIME rejection helper (`BankMimeVerificationService.php:226-232`). It does not mean verification was skipped or failed again.

`BankInboundEmailService.php:52-62` rechecks owner and parses before Store SEPA dispatch. `TatraBankEmailParser.php:32-43` can return a permissive classic or compact row even when strict evidence is null. Classic E2E/explicit VS/identity come only from strict evidence (`:115-119`); compact always has isExplicitCredit false (`:171-185`). These are possible code paths, not claims about this unseen notification. Strict parser rejects multiple structure/content/date/account/reference/tail cases; no exact current-bank failure is inferable from the broad audit.

Owner confirms the new original is unavailable and only Mailgun logs exist. Old capture is empty and the existing capture trigger is invalid_signature only (`BankMimeVerificationService.php:150-159`). No payload or fine-grained historical reason can be recovered from the audit, missing capture or delivery logs alone. New metadata cannot backfill this event. No capture renewal/trigger expansion, raw logging, forced-paid change, replay or new transfer follows.

## Accepted metadata-only contract

On incomplete_credit_evidence only, preserve the original reason, eligibility predicates, public return shape, owner binding, provider call order and retry behavior. Add one fixed nested credit_evidence version 1 object: credit count clamped to three, truncated boolean, first three per-credit check sets. Each set contains booleans for the five exact current gates and informational strict_profile_identity presence; trusted_reference means the existing referenceCandidates()!=[], never inferred/raw reference text. Identity presence must not become a sixth eligibility gate.

A pure first-failing strict-profile stage shares exactly the same evaluation as parse(string):?array, preserving evidence/reference casing/identity/null behavior and validation order. Stage evaluation uses the existing verified Tatra family/profile selection and bounded body; other bank/input paths yield not_evaluated. Fixed enums only, including shared greeting-or-line-count guard semantics. No body/headers/account/address/amount/currency value/reference/identity/hash/timestamp/row ID, arbitrary exception or DTO serialization enters metadata. Catch all diagnostic-only failures to fixed unavailable without changing the original review reason/result. No new I/O, financial call, retention or queue action.

Resolved pre-freeze feedback: an initial stage guard required literal root tatrabanka.sk, incorrectly excluding already supported aligned Tatra subdomain proofs. Frozen service lines 180-184 now require existing actual-domain alignment plus Tatra-family sender matching. Both aligned subdomain regression cases passed independently. This was diagnostic coverage feedback, not a financial bypass or an open finding.

## Frozen implementation and independent checks

The unchanged five eligibility predicates are at frozen `SepaEmailConfirmationService.php:52-56`. New diagnostics at lines 165-190 project only six booleans for at most three credit rows, a clamped count/truncation flag and fixed pure stage. The stage uses existing verified Tatra-family selection, with a bounded body. No DTO/raw values are serialized. Review lines 194-206 preserve the original reason and result, nesting all additional data under credit_evidence; no metadata can overwrite the reason. Diagnostic-only exceptions at lines 58-62 become a fixed unavailable object before the same review return. The accepted/provider/dedup path starts after this branch and is byte-identical to baseline.

`TatraBankSepaEvidenceParser.php:24-123` provides parse/diagnose wrappers around one pure evaluate path. Apart from fixed stage assignments, all validation, reference casing, balances, identity construction and rejection order match baseline. It retains no per-message or global mutable diagnostic state. Stages are fixed literals; unsupported content and payer-controlled labels cannot become a parsed reference through diagnosis.

Verified attack/regression scenarios: every individual eligibility bit false; inferred versus explicit VS and ambiguous references; more than three rows; private markers in sender/body/reference; diagnostic exception text; absent/non-string/oversized/non-Tatra body; aligned Tatra subdomains; each strict rejection stage; existing valid credit lacking strict identity; and unverified/no-credit paths. Tests prove fixed bounded metadata, unchanged reason/outcomes, zero provider calls on review and unchanged success. No source finding was identified.

Reviewer-executed results:

- **PASS: 59 new unit/feature tests / 224 assertions, zero skips**, PHP 8.3.31/PHPUnit 11.5.50, 3.693 seconds. Exact files: TatraBankSepaEvidenceDiagnosticsTest and SepaCreditEvidenceDiagnosticsTest. Read-only source mount, separate security prefix and `--do-not-cache-result`.
- **PASS: 450 independent baseline/parser/stage/privacy assertions across 90 synthetic inputs.** An exact git-show copy of the 12f83c11 parser was renamed under `/tmp` solely for side-by-side class loading. Executed without application/DB bootstrap in a network-none, read-only container. Exact evidence arrays, E2E/VS case, financial identity and null decisions matched; stages were allowlisted, matched iff evidence existed, and diagnosis did not mutate later parse output. Corpus included valid formatting/reference/tail variants, every line replacement/removal/duplication, invalid/DST dates, zero/negative/ambiguous money forms, controls/entities/Unicode, currencies and references. No real message or private merchant data.
- **PASS: structural baseline comparisons** of exact five eligibility predicates, complete accepted financial/settings/report/reference/dedup path, and strict-parser validation body after removing only stage assignments/whitespace. Eight existing MIME/ASCII/inbound/general-parser/capture/queue/sanitizer files are byte-identical to 12f83c11.
- **PASS: all four supplied frozen hashes verified after testing; git diff --check passes.**

Test environment/runner inspected: testing and uncached configuration, SQLite memory, array cache/session/mail, sync queue, null logs, synthetic provider/key, existing locked dependencies. Reviewer directly inspected the exact disposable Redis: network none, no ports, loopback bind, save disabled, appendonly no. Tests used only that namespace and `bank_mime_capture_credit_evidence_security_`; no production services/data. Normal Docker escalation succeeded; no automatic approval rejection. No installs, real funds, live mail or production command.

Attributed evidence, not reviewer-run counts: author reports 146 tests / 526 assertions with zero skips, four-file Pint, narrowed PHPStan and structural parity passing. Main reports full PHPStan and API export/check passing and 19 preserved ingress/capture/Node/dependency/persistence files. Independent QA's broad bank regressions and fresh exact-head CI remain main's gates; this report does not claim those ran here.

## Frozen reviewed manifest

| Relative file | SHA256 |
| --- | --- |
| `app/Services/SepaEmailConfirmationService.php` | `ef10404dafdc70866db17f613f2b690b65a888888f7c433641bb81af6d5a77af` |
| `app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php` | `155e8fac2621b791225ee83a01c9305972c3bc88bd596ca3653fc5cb918a9243` |
| `tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php` | `16d12714dfca868ae2de2d2e486535ff12754fabe31f637ef34944773c6bab80` |
| `tests/Feature/SepaCreditEvidenceDiagnosticsTest.php` | `33b11850a95fa1d3b8715b228604f9f02f1e5578b541054e1f5df13ec7721324` |

Runtime acceptance is bound to these hashes. Main's contract/runbook/generated artifacts may be finalized separately; no later runtime delta is implicitly accepted. Existing auth/CSRF, tenant ownership, merchant secrets/recovery, billing/entitlements, local-first privacy, MIME proof and raw capture behavior are unchanged by this bounded diff, not freshly audited wholesale.

## Operator projection and remaining limitations

The runbook query was reviewed read-only: latest-three SELECT, only timestamp/reason/credit_evidence, bootstrap inside catch and PHP display/log errors disabled; no financial/queue/capture calls. Reviewer requested server-side selected Store and incomplete_credit_evidence filters because the initial all-Store/all-review query could mix incidents and unnecessarily project another merchant's diagnostics. **Resolved and accepted** in runbook SHA256 `9391a72de697c86f6ed3d6cbd6c0075cd7ebcb6c6a96928e8a19a0565b8eb51f`: lines 146-174 take a server-local quoted environment UUID, validate its shape and Store existence, filter target_type/target_id/action/reason, limit to three and emit only the three safe fields. No UUID is echoed; the host variable is unset. The quoted heredoc avoids shell interpolation. This documentation-only correction required no runtime retest. Contract SHA256 `12ca1561afce7116d1148e842aa684fd7fd7f466a7a5890569081f1201d1be44` also matches the accepted bounded metadata design. Never broaden output to whole metadata or log real bank values.

No current original exists, and no actual bank template/failing stage has been established. The 20:15 audit cannot be backfilled. This source only explains a later processed notification's existing decision without retaining new content. No capture trigger/TTL change, transfer, replay or forced-paid action is required or authorized. Source and operator-projection security are accepted with no unresolved finding; main's final QA/CI and applicable publication/deployment gates remain separate.
