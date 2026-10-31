# Independent QA: PR412 bank-mail reference and outcome review

Reviewer: `sepa247_qa`, independent of implementation authors. Reviewed base: `4b607bb20b6c29c36ae5d113ca743490c0699044` plus the frozen review fixes. **Accepted for the bounded source/runtime scope; no blocking finding.** No merge or deployment authorization is implied.

## Frozen scope and environment

Independently byte-checked all 17 files in the coordinator's reviewed manifest after the final checks. Manifest SHA-256: `33f6dfdaefe23b14b2dde8f23fdd98741b294e49234f58365b03545c24cc4304`. The shared integration prose is outside that functional/schema/test/locale snapshot; later report-only publication edits do not imply new executable qualification.

Before execution, inspected the disposable PHP runner: no network, SQLite in memory, synthetic application/provider settings, exact dedicated vendor mount, fixture-only memory configuration and no cached app config. No production environment or database was read. The primary Graphify query returned the Tatra parser node; target-worktree graph is absent. No graph installation/rebuild occurred.

## Executed independent regressions

1. Existing focused projects: `php artisan test tests/Feature/SepaBmailTest.php tests/Unit/SepaBankEvidenceTest.php tests/Unit/TatraBankEmailParserTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaTest.php` inside the verified disposable wrapper: **103 tests / 276 assertions passed**, 5.51 seconds.
2. Separate reviewer-owned temporary fixture: **5 tests / 36 assertions passed**, 0.51 seconds. It is a historical one-off review fixture, not a committed repository entry point or placeholder command.
3. Frozen-file digest comparison: all 17 files match. `git diff --check` passes.

Total independent execution: **108 tests / 312 assertions passed**. No broad suite or unchanged frontend/provider execution was repeated.

## Adversarial reference and generic-import evidence

The temporary fixture loads a renamed copy of the base-revision parser alongside the frozen parser. Five cases vary payer-controlled reference/VS labels after a genuine classic credit header, including standalone lines, punctuation, HTML-normalized notes and nested/mixed references. For each case, old and new `toRow()` import data compare exactly. The original parser promotes the injected QR to automatic-reference metadata; the corrected parser leaves E2E/explicit-VS automation eligibility unset.

This proves the targeted trust-boundary correction without pretending a bank signature makes payer remittance a bank-owned transaction field. Generic company-import amount, direction, symbols, reference and date metadata remain unchanged. Existing bank-import regressions also pass. The same inherited parser behavior keeps SLSP/generic unbounded formats from gaining SEPA automatic matching.

## Outcome and schema evidence

The focused suite executes the documented provider `unknown` outcome alone and in multi-row orders with settled/review results. Unknown rows require manual review, do not increment `reported`, and cannot have their review requirement hidden by a later successful row; audit counters match. Retry identity and old-provider capability checks remain covered. This statement concerns the documented `unknown` enum result, not a claim of arbitrary malformed-provider-response normalization.

Independent schema assertions verify that native `bank_authentication` remains optional at its parent, while a present object requires exactly `dkim_result`, `dkim_domain` and `dmarc_result`. Parent legacy required fields remain `to`, `from` and `body`.

Independent real-webhook requests with empty-object and partial attestations are accepted with the expected job acknowledgement. Queue inspection and direct verifier assertions confirm their verified-bank-domain marker stays null. The client schema's complete-attestation contract is intentionally stricter than the permissive legacy receiver; no new receiver validation was added, and accepting incomplete metadata grants no bank-origin privilege. Existing unverified-origin/manual-review tests pass.

## Limits and attribution

Current bank-mail formats remain manual for SEPA matching because no bank-owned reference boundary is proven. This does not disable unchanged generic company imports or claim that every automatic SEPA source is disabled. A future structured bank format needs independently established provenance before enabling reference flags.

The coordinator owns API-export, full static/style/locale gates and fresh exact-head CI; this reviewer does not relabel those runs as independent execution. No source/shared-state/Git writes, installs, external comments, bank/provider HTTP traffic, merchant tokens, wallets, funds, production state or deployment actions occurred. Only private review fixtures, logs, this report and its relative-file manifest were written.
