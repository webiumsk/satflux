# Private bank MIME capture - Node implementation report

Repository: Satflux (`webiumsk/satflux`), branch `feat/bank-mime-private-capture`.
Base revision: `037a6fa7e739597b6c54afb4b02d37a277dd3cc5`.
Author: `/root/bank_mime_capture_node`. This is implementation evidence, not independent security approval.

## Scope and interface

Only the three assigned Node files were authored: `runtime/bank-mail-verifier/verifier.mjs`, `diagnostic-cli.mjs`, `test/diagnostics.test.mjs`. No PHP/config/docs/dependency or company-runtime changes; no Git publication or production access.

`diagnose(input, { resolver } = {})` returns `{verification, diagnostics, contentSha256}`. `verification` is the existing result produced by the shared internal evaluation. The complete original bank-proof predicate is byte-identical to base. Original prechecks and retry predicates remain unchanged. Proof/retry output is finalized before a guarded diagnostic-only callback. Callback failure leaves verification unchanged and an empty signature list.

`diagnostics` has exactly `{version:1,status,reason,signatures}`. Each of at most five entries has only the fixed verdict enum/null, nullable bank signer/alignment/allowed-algorithm/time/full-body facts, and six fixed `{required,signed}` header facts. Missing numeric body lengths cannot accidentally compare equal as undefined. Precrypto rejection produces `signatures:[]`. Unknown string verdicts become `unknown`; absent/nonstring values become null. The diagnostics object contains no domains, selectors, keys, arbitrary header names/values, comments, addresses, body, subject or signature content.

`contentSha256` binds exact UTF-8 raw MIME bytes including early rejected messages; it is null for missing/nonstring or malformed-Unicode raw input. It exists only in the private process envelope, outside diagnostics, for PHP equality validation. It is not bank proof. The internal envelope can contain the same verified body/from/subject as strict CLI. PHP must never print/log it; operator output is diagnostics only.

`diagnostic-cli.mjs` accepts the same two input fields via stdin only, with the same 1,576,960-byte JSON transport bound and 13-second hard timeout as strict CLI. It has no custom DNS/resolver/JSON/environment override. Fixed transport failure envelopes preserve status/reason and any already parsed input digest. Existing strict CLI bytes remain unchanged.

## Verification

Existing locked dependency directory supplied by main was linked read-only; no install. Runner: `/tmp/bank-mime-capture-tests/run-php.sh`, existing Node 24.22.1, disposable environment without external networking. DNS in tests is generated synthetic public-key TXT data with in-memory keys or the existing test-only preload. No actual bank message, production database, wallet, merchant credential, transfer or real funds.

- `... node --test runtime/bank-mail-verifier/test/diagnostics.test.mjs`: PASS 14/14, zero skipped, 17.725 s.
- `... node --test runtime/bank-mail-verifier/test/*.test.mjs`: PASS 56/56, zero skipped, 17.826 s. Includes all original 42 verifier/company cases.
- Host Node 22.18.0 `node --check` for all three assigned files: PASS.
- Scoped `git diff --check`: PASS.
- Scripted byte comparison: strict CLI, package manifest/lock, company CLI/extractor match base HEAD exactly; complete bank-proof predicate matches base exactly.

New cases cover actual valid signatures; five separately missing signed required headers with crypto PASS; future/expired signatures; body/header crypto failure; a weak synthetic DNS key; permanent key-not-found and temporary DNS error; unsupported signer without attacker DNS lookup; malformed signature with null unavailable facts; precrypto input/MIME/identity/size rejection; five-signature bound and six-signature rejection; no proof mixing across separately passing partial-coverage signatures; real strict/diagnostic CLI result equivalence with private DIAG markers; malformed JSON, rejected resolver injection, ignored script arguments, input bound, and actual 13-second timeout.

The first author run was 12/14 because the fixture signer itself rejects 512-bit signing keys and the test launcher mistakenly passed script flags to Node before the script. Tests were corrected to supply a weak public DNS key to a normally signed message and pass the ignored arguments after the script; final complete suite passes. No implementation-policy change resulted.

## Frozen hashes (SHA-256)

- verifier.mjs: `0a1ea5b71bd8903af8983253abd8203bfbf4f38ffd83c3dab70107c0f25c99da`
- diagnostic-cli.mjs: `8ec493e19e5400037cca56ce59a5e9f84b86b0da265a56b14163890848fa348c`
- test/diagnostics.test.mjs: `0dca44ff6b17cf9b3f2020041d84e45e102b4f6c8a43be73b0db1d5c6614f858`
- unchanged cli.mjs: `bfddc7a1a4c5f641a8682a088fa054dcaccc32051920dcbd3212ef8bf89ac4b0`
- unchanged package-lock.json: `2df2a5a08cd03d4e92105907dee3025f4a9a2ea7690103e3a4b001e7ff270f03`

## Dependencies and remaining gates

Main owns PHP bridge validation, encrypted original/current-summary separation, operator privacy, capture lifecycle and publication. Independent QA/security review remains required; implementation cannot approve itself. Fresh exact-head Node 22 CI remains separate from the isolated Node 24 run. Graphify had no worktree graph; primary query was already performed by main and had no MIME edges. No graph installation/rebuild.
