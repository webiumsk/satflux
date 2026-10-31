# Independent QA: PR412 CodeRabbit UTF-8 and guidance follow-up

Accepted for the frozen six-file source/doc delta. No blocking finding. Reviewer: independent `sepa247_qa`; repository `webiumsk/satflux`, base `52fb0e0f9977bbca809e3ca48060a431c9c6945a` plus the six files in `qa-reviewed-source.json`. Review: CodeRabbit `5720326942`. All six hashes were independently calculated after the final freeze and verified unchanged after execution. Source, shared state and Git stayed read-only; evidence was written only to private temporary QA storage.

## Actual execution and environment

`php artisan test tests/Feature/BankMimeConfirmationTest.php tests/Feature/ApiDocumentationTest.php` ran through the inspected existing offline disposable PHP runner, Node **24.22.1**, exact existing Composer vendor, SQLite **:memory:**, synthetic credentials, array sessions/cache/mail, no cached configuration and Docker **--network none**. Result: **22 passed / 283 assertions**, zero skipped cases; successful process exit. `git diff --check` also passes. No dependency install, database seeding outside the disposable fixture or production access occurred. Graphify query tooling was unavailable this turn; targeted reads were used without graph installation/rebuild.

The new full form-urlencoded MIME regression contains a deliberately invalid UTF-8 byte (an ISO-style eight-bit body), passes real Mailgun delivery HMAC and asserts HTTP acceptance, no verifier invocation, no outbound provider request and exactly one manual-review audit with the non-sensitive stable reason. The implementation checks raw bytes after ciphertext decryption and the size bound, before JSON encoding or any subprocess call. Unsupported encoding is therefore permanent manual review rather than a process-error queue retry. Existing valid synthetic DKIM-to-parser-to-report, ciphertext preservation, tamper/retarget rejection and genuine transient retry/privacy tests remain passing in the same 11-case MIME suite. The other 11 cases verify API access controls, final regenerated export fingerprints and supported webhook variants.

This bounded follow-up did not repeat the unchanged Node crypto, strict transaction parser, full PHP/frontend, typecheck or lint suites. Their earlier results remain historical evidence. The coordinator's independent legacy-company suite and security review are not represented as this reviewer's execution.

## Guidance review

The former manual-only capability record is explicitly historical and links to the later automatic-MIME qualification; the target exists. The design prose now distinguishes the pre-candidate lack of an independent verifier from the current implementation. The runbook separates store `ps` addresses to the original-MIME endpoint from company `pay` addresses to the existing parsed endpoint, requires non-overlapping routes/stop actions and explains why a shared MIME catch-all would acknowledge company deliveries before rejecting them in processing. Defaults match config and source recipient parsing. The company example's 8..16 token routing envelope is broader than current generated/resolved company tokens; it does not grant origin or ownership privilege and unknown recipients are still rejected by the existing resolver. Operators are instructed to adapt domain/prefixes to actual configuration. No live Mailgun route was changed or inspected.

## Independent post-delivery artifact verification

The two delivered archives still equal their independently accepted source-e8d7fbc artifacts:

- Raffle **1.3.2.6**, 3,288,937 bytes, SHA-256 `0fe076bfbcbadc917bc00c9f56cd2328b4ca5aee63052ce458858468328ceff2`.
- Tickets **2.2.3.2**, 12,974,317 bytes, SHA-256 `6d6779a7da3eaa774f0c57e6de51fad18ae63ca84f40af03227983c29359d80a`.

All **70** pre-existing archives and **four** original metadata/checksum files independently match their captured baseline after delivery, including unchanged SEPA 0.8.2.0. Details are in `post-delivery-verification.json`. No plugin rebuild, binary/source change, reinstallation or payment execution was required or performed for this PHP/doc follow-up.

No live bank EML, actual Mailgun routing, real bank/remote-provider settlement, installed-host execution, production data, deployment, merge or publication is qualified by this review. Current-bank format coverage remains unverified and the strict supported subset still uses historical structure plus synthetic cryptographic evidence. Main retains source publication/CI and operational ownership.
