# Tatra ASCII wire profile - Node implementation handoff

Repository: `webiumsk/satflux`, branch `fix/tatra-ascii-wire-profile`, base revision `406e1fefb9840d1ac851a7fdbf337f84eba0b645`.
Author: `/root/bank_mime_capture_node`. This implementation report does not constitute independent security approval.

## Completed bounded scope

Owned files only:

- `runtime/bank-mail-verifier/verifier.mjs`
- new `runtime/bank-mail-verifier/ascii-wire-profile.mjs`
- new `runtime/bank-mail-verifier/test/ascii-wire-profile.test.mjs`
- new test-only `runtime/bank-mail-verifier/test/build-ascii-wire-message.mjs` (additional ownership authorized by main)

Both existing CLI entry points, package manifest/lock, Company runtime, all existing test files and existing strict build-message fixture producer remain byte-identical to base. No PHP/config/docs/shared-state edits, dependency installs, Git publication, real message inspection or production operation.

The complete legacy eligible-bank-proof predicate remains byte-identical and is evaluated first (`verifier.mjs:160`). Legacy verified outputs preserve the original decoded text, field shape and precedence, including fully signed QP/base64. New fallback selection (`verifier.mjs:172`) is attempted only without a legacy proof. Admission requires MIME1.0, root plain/UTF8 (bounded ASCII formatting, optional charset quotes/case), and 7bit. Unsupported unsigned metadata rejects; accepted metadata cannot choose text interpretation.

A single same actual DKIM result must pass bank signer/alignment, allowed algorithm/key policy, signature-time, complete body, signed From/To/Subject and the unchanged global no-l guard. That result must supply the known explicit relaxed/relaxed token, SHA256 `bodyHash` and `bodyHashExpecting`, strict false body-limit flag, and both exact safe-integer canonical lengths. Only the known token is case-folded; no trimming, defaults, alternative mode or one-component interpretation. A real uppercase token test confirms library crypto acceptance.

The pure helper independently projects the entire raw body using a single linear byte/code-unit scan (`ascii-wire-profile.mjs:8`). Input is restricted to printable ASCII plus SP/HTAB and paired CRLF, with raw wire and canonical body each <=65535 bytes and physical line <=998 bytes. It collapses SP/HTAB to one separating SP, discards line tails and terminal empty lines, preserves internal CRLF boundaries and adds final CRLF only for nonempty canonical output. It returns no decoded QP/base64/HTML/entities, charset conversion or guessed interpretation. The result's exact SHA256 must match both same-proof hashes and its exact length must match both same-proof lengths (`ascii-wire-profile.mjs:48`). Empty relaxed body maps to empty text/hash, consistent with the actual pinned implementation; no transaction claim follows from that.

Successful fallback output uses exactly the canonical ASCII text plus fixed internal `bodyProfile:'tatra-ascii-relaxed-v1'`. Raw `contentSha256` remains the exact original MIME digest. All fallback proofs share one fixed relaxed projection, so changing order/removing other eligible proofs cannot choose another body. Fully signed legacy proof precedence is explicit and tested.

Diagnostic schema/version/key set stays unchanged. Only after selecting a successful fallback does the observer mark its required header names as From/To/Subject; MIME facts remain present and correctly show unsigned/not required. Existing invalid and legacy diagnostic behavior remains unchanged. No profile/digest/body/private value is added to diagnostics.

## Test-only PHP integration fixture

`test/build-ascii-wire-message.mjs` stdin: `{recipient,body}`. Stdout is exactly the existing fixture producer shape `{rawMime,dnsName,dnsRecord,selector}`. It uses the existing actual synthetic fixture signer with UTF8/plain/MIME1.0/7bit, relaxed/relaxed and signed From/To/Subject only. Private keys remain in memory and never enter output. Production CLIs gain no fixture options. Main and independent QA can use this file for actual Node-to-PHP body/evidence/credit-identity integration tests.

## Executed verification

Existing locked deps supplied through the established read-only container mount; no install. Existing Node24.22.1 in `/tmp/tatra-ascii-wire-tests/run-php.sh`, disposable app/Redis namespace with no external network, generated synthetic keys and in-memory TXT resolvers only.

- New suite: `... node --test runtime/bank-mail-verifier/test/ascii-wire-profile.test.mjs`: PASS 21/21, zero skipped, 3.571 s.
- Complete suite: `... node --test runtime/bank-mail-verifier/test/*.test.mjs`: PASS 77/77, zero skipped, 20.533 s, including all 56 existing tests unchanged.
- Host Node22.18.0 syntax checks for all four owned files: PASS.
- Scoped `git diff --check`: PASS.
- Scripted original/current byte comparison: complete legacy proof predicate, both existing CLIs, manifest/lock, Company runtime, all three original test files and strict build-message producer: PASS unchanged.

New coverage includes actual unsigned-control positive verification; signed legacy shape/text/precedence; header spelling invariance; unsupported charset/CTE/type/version/removal/duplicate/disposition controls; encoded-looking text stays literal; every forbidden ASCII control and representative non-ASCII/BOM/bidi whitespace; malformed newlines; independent raw/canonical/line bounds; RFC relaxed edge vectors and 32 deterministic differential real-DKIM whitespace cases; missing/malformed/mismatched same-proof hashes/lengths/modes; simple and mixed modes; actual re-signed uppercase/omitted/one-component c= tokens; missing signed identity, wrong sender/recipient, RSA-SHA1, weak DNS key, future/expired/no-l/permanent/transient DNS failures; body/amount/subject/signature tamper; WSP equivalence; signature reordering/removal, mixed-mode ordering, no cross-proof coverage/time synthesis; diagnostic v1 parity; and test-only fixture output shape/no private key.

All executed final author suites passed. An exploratory unsigned-From signer probe confirmed the library refuses to generate such a signature; a modified h= negative is therefore used to verify fail-closed behavior. RSA-SHA1 can be generated with test-only signer leniency and is rejected by production verification. No production leniency was added.

## Frozen SHA256

- verifier.mjs: `dfc646f7a8eeebd85cc72255a591230d4c00b43626140fab462d2c90b101e40a`
- ascii-wire-profile.mjs: `c3f60f6f20468cf38e2baf93e4282198a60e471167a0d79c8f22728142cb82ef`
- test/ascii-wire-profile.test.mjs: `98e4ae512d78405fe22fe1fdd502030d77a81b2bd65ee5d6b9e953c6e0595395`
- test/build-ascii-wire-message.mjs: `cb7a978cdaf4251b41ffeff71dad97a02489b3cd50fc084a7854deca1ecf2c3d`

## Dependencies and remaining gates

Independent security and QA must review the frozen candidate; this author cannot approve its own security review. Main owns pure outer profile diagnostics, validated PHP transport, complete strict credit-frame evidence and cross-profile credit identity/replay tests, documentation/manifests and publication. Fresh exact-head Node22 CI is distinct from Node22 syntax checks and the actual Node24 suite. Actual retained-bank transaction layout remains unverified by agents and must not be inferred from synthetic crypto success. Existing capture/retention and financial operation boundaries are unchanged.

Graphify worktree graph is absent. Main had already queried the primary graph and found no relevant MIME edges. No graph rebuild or tool install was performed.
