# Credit evidence rejection diagnostics - backend delivery

Repository: Satflux core.
Assigned worktree: `storage/app/private/worktrees/tatra-ascii-wire-profile`.
Branch: `fix/tatra-ascii-wire-profile`.
Reviewed base: `12f83c11f326eb3d5c658ab8c18262b149f9db8d`.
Implementation author: backend specialist; independent security/QA decisions belong to the assigned reviewers and main.

## Approved scope and completed contract

Metadata-only explanation of the existing `incomplete_credit_evidence` review. No financial decision, parser eligibility, reference trust, receiving-account guard, provider/report contract, deduplication, public result, log context or capture/retention behavior changed.

Owned files only:

- `app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php`: existing `parse(string): ?array` delegates to a shared private evaluation path with a by-reference diagnostic stage. All original validation statements, ordering, regular expressions, null returns and conservative identity schema remain unchanged. `diagnose(string): string` exposes only the first failed existing stage: `unsupported_content`, `greeting_or_line_count`, `credit_header`, `credit_datetime`, `creditor_or_amount`, `balances`, `transaction_description`, `trusted_reference`, `tail`, or `matched`. No parsed values are returned by the diagnostic method.
- `app/Services/SepaEmailConfirmationService.php`: the original eligibility filter remains byte-identical. Only its existing empty-eligible branch adds `credit_evidence` to the review audit. It contains version 1; credit_count capped at 3; truncated boolean; at most three eligibility objects containing explicit_credit, explicit_currency, unambiguous_reference, valid_receiving_account, trusted_reference and strict_profile_identity booleans; and one fixed tatra_profile_stage. The first five checks exactly mirror the existing eligibility conjuncts, including the existing referenceCandidates method. Identity presence is informational and is not a new eligibility rule. The stage runs only for bounded string bodies whose actual verified domain/from match through the existing verifier and whose sender also matches the existing Tatra matcher, including already-supported aligned Tatra subdomains. Other payloads get `not_evaluated`. Diagnostic exceptions produce only `{version:1,status:unavailable}` under credit_evidence; the original review result/reason survive and exception text is discarded. Metadata cannot replace the outer reason.
- `tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php`: supported historical adaptations and hostile frames cover every stage, deterministic first-failure precedence, expected evidence/identity, diagnostic non-mutation and privacy.
- `tests/Feature/SepaCreditEvidenceDiagnosticsTest.php`: every existing eligibility predicate, reference ambiguity/VS semantics, max-three bounds and capped counts, verified Tatra subdomains, unsupported diagnostic inputs, raw-value exclusion, diagnostic exception privacy, no provider getSettings/report calls on incomplete evidence, unchanged empty/unverified outcomes, and accepted flow without a strict identity.

Main owns concurrent documentation/schema/export changes. This agent did not edit or revert them. No other repository files were modified by this agent.

## Frozen SHA256

```text
ef10404dafdc70866db17f613f2b690b65a888888f7c433641bb81af6d5a77af  app/Services/SepaEmailConfirmationService.php
155e8fac2621b791225ee83a01c9305972c3bc88bd596ca3653fc5cb918a9243  app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php
16d12714dfca868ae2de2d2e486535ff12754fabe31f637ef34944773c6bab80  tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php
33b11850a95fa1d3b8715b228604f9f02f1e5578b541054e1f5df13ec7721324  tests/Feature/SepaCreditEvidenceDiagnosticsTest.php
```

## Environment and exact validation

Read role and Laravel/payments skills, repository instructions, project state/roadmap/decisions/workflow/registry, CLAUDE, SECURITY and applicable Cursor rules. Graphify tool and worktree graph are unavailable; main's existing graph is stale for current parser edges. Used targeted assigned-path exploration; no graph rebuild, tool or dependency installation.

Main approved an isolated copy of the existing synthetic runner at `/tmp/bank-credit-evidence-backend/run-php-no-redis.sh`: Docker `--network none`, capture Redis tests disabled, same existing read-only dependency mounts, synthetic credentials and environment overrides. No dependencies, repository env files, mount directories or existing runner were edited. Effective Laravel bootstrap probe before PHPUnit confirmed testing mode, uncached configuration, SQLite `:memory:`, array cache/session/mail, sync queue, null logging and synthetic BTCPay URL. No Redis service was needed or started by this agent.

```text
sh /tmp/bank-credit-evidence-backend/run-php-no-redis.sh php artisan test tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php tests/Feature/SepaCreditEvidenceDiagnosticsTest.php tests/Unit/TatraBankEmailParserTest.php tests/Feature/SepaBmailTest.php
PASS: 146 tests / 526 assertions / 8.50s; zero skipped tests reported.

sh /tmp/bank-credit-evidence-backend/run-php-no-redis.sh vendor/bin/pint --test app/Services/SepaEmailConfirmationService.php app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php tests/Feature/SepaCreditEvidenceDiagnosticsTest.php
PASS: four assigned files.

sh /tmp/bank-credit-evidence-backend/run-php-no-redis.sh vendor/bin/phpstan analyse --no-progress app/Services/SepaEmailConfirmationService.php app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php
PASS: existing phpstan.neon.dist/baseline; no errors. No baseline or ratchet changed.

git diff --check
PASS.
```

The first Pint check found one multiline whitespace rule in the new feature test. Scoped Pint corrected only that formatting issue; the final check passed. There were no PHPUnit or PHPStan failures. The targeted PHPUnit pass preceded that whitespace-only correction; all production sources were already at the frozen bytes.

Additional author structural comparison against `git show 12f83c11` passed:

- Original strict-parser validation and identity statements are identical after removing only fixed stage assignments and ignoring indentation/blank lines.
- Existing validTail/safeFreeText helpers are byte-identical.
- Original eligibility filter is byte-identical.
- Original owner/API-key/settings/reporting/dedup/referenceCandidates path is byte-identical.

Independent QA is separately performing old-revision/new-revision runtime corpus equivalence, broader regressions and review; main owns full static/API/CI gates. No duplicate broad suite was run by this author.

## Limits, assumptions and remaining gates

An existing incomplete-credit audit establishes that origin matching passed and credit rows existed, while all rows failed the existing eligibility filter. It does not identify the actual historical missing bit or current-bank frame without the original data. This metadata supports future diagnosis only; no actual original was available to the agents. `matched` means only that the existing strict frame matched; it is not invoice matching, report acceptance or settlement. Presence of sepaCreditIdentity is informational only.

No current raw bank content, merchant identifiers, account/reference values or real payment data were used. No real funds, replay, provider operation, raw retention, capture extension, new activation, production access, commit, push, merge, deployment or external publication occurred. No backend blocker remains; independent review and main publication gates are pending.
