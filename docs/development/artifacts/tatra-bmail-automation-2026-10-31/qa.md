# Independent QA: automatic Tatra B-mail

Disposition: accepted for the reviewed source implementation and synthetic qualification scope. No blocking defect was found. This is not production or current-bank interoperability acceptance.

Reviewer: independent `sepa247_qa`; authors and coordinator did not execute this review. Repository: `webiumsk/satflux`, base `cd951b3b7e27a2f30278c0d414d603f483b83f62` plus the frozen automatic-Tatra working diff. `qa-source-manifest.json` records independently calculated SHA-256 values for all 40 functional/runtime/API/CI/locale files and matches the coordinator's final 40-file capture without mismatches. Source and shared state remained read-only; only disposable QA fixtures and evidence were written. Graphify primary query was used before targeted exploration; no graph or dependencies were installed or rebuilt.

## Actual independent execution

All final PHP commands used the inspected offline disposable runner: `satflux-php:latest`, existing Node **24.22.1** binary mounted read-only, exact existing Composer vendor, SQLite `:memory:`, synthetic test credentials, array sessions/cache/mail and `--network none`. No cached production configuration or production database was used. The commands below are the exact Artisan/Node invocations inside that verified environment.

| Command | Actual result |
| --- | --- |
| `node --test runtime/bank-mail-verifier/test/verifier.test.mjs` on native Node **22.18.0** | **26 passed**, 0 failed/cancelled/skipped/todo |
| `php artisan test tests/Feature/BankMimeConfirmationTest.php tests/Feature/ApiDocumentationTest.php` on Node24 | **21 passed / 277 assertions** |
| `php artisan test tests/Unit/TatraBankEmailParserTest.php tests/Feature/SepaBmailTest.php` on Node24 | **87 passed / 302 assertions** |
| `php artisan test /qa/VerifierOutputLimitTest.php` on Node24, one-off private fixture | **1 passed / 2 assertions**, 0.68 s |
| `git diff --check` | Pass |

Final committed-source PHP entry points therefore passed **108 tests / 579 assertions** independently; adding the one-off fault injection yields **109 / 581**. Node's 26 cases are separate and are not PHPUnit assertions. No tests were skipped. An earlier Node20 diagnostic run is not runtime qualification. Initial default-sandbox Docker/process-start failures were retried through normal approval; failed starts are not counted as passes. The final repeat used the exact post-output-cap and post-reference-case-fix source, including regenerated API coverage.

## Contract and adversarial evidence

- `BankMimeConfirmationTest` exercises actual authenticated Mailgun `body-mime` ingress, byte preservation including trailing CRLF, ciphertext-only serialized job data, production Node CLI with real synthetic DKIM and offline public DNS, the production strict parser and the outgoing provider contract. Positive crypto evidence is not an injected verified DTO. The provider HTTP endpoint is faked, so this proves the outgoing request rather than actual remote settlement.
- Actual MIME tests reject unsigned/parsed-only and oversized delivery, reject changed bank body despite valid Mailgun delivery HMAC, and reject a valid signed message retargeted to another store. Transient/broken verifier processes preserve the same encrypted retry payload. Downstream failure cannot retain decrypted content in the thrown exception chain or serialized job.
- Native Node22 tests execute the production crypto/CLI using pinned **mailauth 8.0.4**, **mailparser 3.14.1**, **joi 18.3.1**. They cover attacker-domain signatures and forged authentication headers, unsigned interpretation controls, duplicate singleton headers, recipient binding, unsupported MIME/encoding, full-body restrictions, signature times, raw/decoded limits, DNS bounds and transient failure. Keys are generated in memory; production JSON cannot supply a resolver or test options.
- `TatraBankEmailParserTest` and `SepaBmailTest` reject missing/reordered bank structure, duplicate or nested references, invalid date/account/currency, debit/zero credit, HTML/entity/control/bidi changes and remittance promotion. Uppercase and lowercase QR references reach the native webhook report unchanged. Case preservation is required for exact provider/NOP reference matching; it is not inferred from the lowercase MIME-positive case.
- Credit identity is a conservative fingerprint over bank-owned account/time/amount/balance/numeric-description evidence. Tests hold it stable across reference, note and rendering changes and verify dedup/retry behavior; altered remittance cannot create a new credit. Unknown provider outcomes require review and do not count as reported. Existing account/store/capability and tenant rejection checks remain covered by the focused service suite.
- `BankMimeVerificationService::verify` applies a live **combined** stdout/stderr cap of 300,000 bytes and clears Symfony buffers. The independent one-off fixture writes 160,001 bytes to each stream and then remains alive; the real method stops and throws the expected output-limit exception in less than one second. Each individual stream is below the cap. This reviewer-only fixture is historical private test support, not a committed executable or deployment instruction.
- Generated API checks pass against the final source, including existing legacy request variants and access controls. The raw-MIME endpoint is separately documented. Review found no new provider-contract or generic-company-import field change; the DTO's SEPA identity does not enter `toRow()`. Full FIO/NOP/company regression suites were not independently repeated in this bounded review.

## Limits and remaining operational evidence

No current original Tatra EML was available. The narrow plain-text grammar is based on public historical structure with synthetic valid-account/QR adaptation. Synthetic DKIM and complete PHP-to-Node execution prove this implementation for that supported subset; they do not prove today's bank signing keys, MIME layout, QR rendering, latency or all bank messages. HTML, unknown layouts and unverified messages intentionally require review. The fingerprint is not a bank-issued transaction ID: indistinguishable legitimate credits can conservatively collide and require manual resolution rather than being credited twice.

No live Mailgun routing/configuration, DNS, receiving MTA, installed provider host, actual bank message, remote settlement, wallet, real funds, package installation, production migration or deployment was tested. Node22 native crypto and Node24 PHP integration are the independently executed runtime pairings; no other runtime is claimed. Separate coordinator full-suite, static-analysis and CI results are not represented as this reviewer's execution. Main retains publication and rollout ownership; independent security review remains a separate gate.

## Deployment dependency integration follow-up

The final source adds one isolated `npm ci --prefix runtime/bank-mail-verifier --omit=dev --ignore-scripts` command after root Node dependency installation in `deploy.sh`. Independent diff review confirms that it uses the nested exact lock, omits development dependencies and disables package lifecycle scripts. Existing `set -e` behavior prevents continuing after a failed install; existing build/deployment ordering is retained. `bash -n deploy.sh` independently passes. No deployment or dependency installation was executed. The source manifest now includes this 41st file; preceding runtime tests remain applicable because no runtime implementation changed.
