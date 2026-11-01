# Tatra ASCII wire-body verification profile

## Observed evidence and repair scope

Owner-run diagnostics of one retained message show a passing aligned bank DKIM
signature, permitted algorithm/time/full-body coverage and signed From/To/Subject.
Only the three unsigned MIME control headers block the existing profile. A
separate safe format probe reports text/plain, UTF-8, 7bit, relaxed/relaxed,
ASCII wire body and no non-whitespace controls. No original bank bytes or private
transaction values are copied into this repository or agent reports.

This repair belongs to the authorized Tatra confirmation work. Independent
architecture/security and QA acknowledge the narrow profile below. It does not
authorize a general unsigned-MIME decoder, financial replay or retention change.
Base406e1fef includes the reviewed capture prerequisite; both PR414 and PR415 are
still open at the latest verified GitHub check, distinct from owner-run deployment.

## Acceptance contract fixed before implementation

1. Preserve the existing fully signed MIME profile, its precedence, decoded body,
   result shape and all admission/security/retry checks. Never merely drop MIME
   names from its required signature list while retaining MIME-selected decoding.
2. A new fallback admits only the observed plain UTF-8/7bit/MIME1.0 profile. Those
   untrusted headers can reject admission but cannot select or transform output.
   Require bounded ASCII wire body containing only printable bytes, SP/HTAB and
   CRLF; raw/canonical body are each bounded to65,535 bytes and physical wire
   lines to998 bytes. Reject non-ASCII, other controls and malformed line endings. No base64,
   quoted-printable, charset conversion, HTML, multipart or transfer autodetection.
3. One same eligible bank signature must pass all current bank/alignment,
   algorithm, time, full-body and signed From/To/Subject checks. Require its
   actual format to be relaxed/relaxed; other modes do not enter this fallback.
   Partial-body signatures remain rejected. Different signatures cannot supply
   different pieces of a proof.
4. Independently derive the complete RFC relaxed canonical ASCII body from exact
   wire body bytes, using a bounded linear algorithm. Verify its SHA256 against
   both bodyHash and bodyHashExpecting from that same passing proof and require
   its exact byte length to equal both canonical body lengths. Return exactly
   that canonical text, bounded by the existing body limit; never mailparser's
   header-selected body. Keep the raw-content digest/recipient binding unchanged.
5. Fallback signatures all use the same fixed relaxed projection, so signature
   order/removal cannot choose another decoding or simple-body interpretation.
   SP/HTAB equivalents map to the same authenticated meaning; whitespace collapse
   must preserve token boundaries rather than concatenate digits or lines.
6. The same selected text must reach the existing strict Tatra credit evidence
   gate and downstream parser. Preserve complete-frame requirements, account,
   amount/currency/reference, provider capability and once-only credit checks.
   No parser/dedupe/history/provider policy changes are included. Generic import
   substring matching is never sufficient evidence for Store confirmation.
7. A successful new fallback may return the fixed internal bodyProfile enum
   `tatra-ascii-relaxed-v1`; preserve the legacy result shape for old successes.
   Safe diagnostics reflect that MIME headers are not signature-binding
   requirements for the selected fallback. Keep the existing summary schema so
   the retained original remains readable; never print arbitrary profile values.
8. Operator diagnosis may add fixed outer `body_profile` and `transaction_profile`
   fields. Pure strict evidence parsing runs only on a validated bounded verified
   recheck body with a recognized profile. Emit matched/unsupported/not_evaluated, discarding all amounts,
   accounts, references, identities and text. These are structural facts, not
   ownership, provider acceptance or payment confirmation. Preserve the final
   same-cipher/deadline check and never invoke import/report/dispatch handlers.

## Ownership and required evidence

| Owner | Exclusive writable scope |
| --- | --- |
| Node author | verifier.mjs, optional dedicated pure ASCII projection helper and dedicated profile tests |
| Main | Pure outer capture diagnostic fields/tests, API manifest/exporter, documentation/state and Git publication |
| Independent security | Read-only final proof/projection/privacy review and synthetic attacks |
| Independent QA | Read-only final Node/PHP/capture/integration regressions with disposable Redis when needed |

Required cases include real synthetic bank signatures with unsigned MIME controls,
legacy full-signature equivalence, all remaining proof requirements, header/CTE/
charset substitutions, body/amount tamper, malformed controls/line endings, hash/
length mismatch, unsupported modes/formats, multi-signature order/no mixing,
SP/HTAB equivalents, cross-profile credit identity and no replay/secret output.
The current retained original may be rechecked read-only after compatible rollout;
diagnostics cannot backfill an already consumed payment or guarantee bank layout
matching. No new real transfer is a test prerequisite.

## Standards and boundaries

[RFC6376](https://www.rfc-editor.org/rfc/rfc6376.html#section-3.4.4) defines DKIM
body canonicalization over wire bytes, independently of later MIME interpretation.
[RFC2045](https://www.rfc-editor.org/rfc/rfc2045.html#section-6.2) distinguishes
identity transfer encodings from encoded representations. The fixed narrow
projection and digest/length checks above are this application's security design,
not a claim that the RFC requires every MIME control header to be signed.

Independent design probes demonstrated that changing an unsigned CTE can preserve
DKIM PASS while changing mailparser text. They also checked canonical body/hash
equivalence and complete-frame rejection cases. Those design results are separate
from final candidate tests, source review, exact-head CI and actual bank rollout.
