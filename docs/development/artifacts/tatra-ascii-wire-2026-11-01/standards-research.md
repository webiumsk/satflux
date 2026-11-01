# Unsigned MIME controls: bounded interoperability research

Research date: 2026-11-01. Reviewer: Codex researcher specialist. Scope: read-only architecture evidence, not implementation/security approval.

## Observed evidence and limits

Satflux primary checkout: `885168f2b3ebaaf70b6724eebe541084c969aa00`, with unrelated dirty work preserved. Parent identifies current receiver source revision `406e1fef`. No application source changes or real-message reads were performed. A Graphify query ran first but returned unrelated/stale nodes; dependency reads were targeted using the parent-supplied location.

Installed dependency observed directly: `mailauth` **8.0.4**, in the existing read-only bank verifier runtime. Evidence below uses package-relative paths, avoiding private host paths. Public upstream references are version-tagged; they are not asserted to be immutable Git commit hashes.

Parent-reported operator facts: one bank-aligned passing signature, full body covered, algorithm/time checks pass, From/To/Subject covered, three MIME controls not covered. No EML, bank identifiers, header values or bank body were provided. Actual transfer encoding, charset and `c=` body mode remain pending. These facts narrow a policy mismatch but do not establish a safe alternate parser.

## Standards findings

### DKIM authenticates a canonical octet string, not a MIME interpretation

[RFC 6376 sections 3.4, 3.7, 5.3.1, 5.4 and 8.1](https://www.rfc-editor.org/rfc/rfc6376.html)

DKIM hashes the encoded wire body; it does not MIME-decode it. Unlisted headers can change without invalidating that signature. Signing MIME controls is advised. Omitting `l=` covers the entire canonical body. Simple mode ignores trailing empty lines; relaxed mode additionally collapses SP/HTAB runs and removes line-ending SP/HTAB. Neither authenticates distinctions it discards. The RFC warns about whitespace-dependent interpretations.

**Engineering inference:** a fixed projection can eliminate unsigned MIME interpretation, provided its input is bound to the same passing signature's entire canonical body. This is a local application protocol, not a general MIME-equivalence claim. Parsing received whitespace and parsing its authenticated canonical form are not automatically interchangeable. Numeric digit separation remains meaningful: `1 000` and `1000` are different canonical strings.

### Transfer encoding and character encoding are separate

[RFC 2045 sections 2.7-2.9 and 6.2, 6.7, 6.8](https://www.rfc-editor.org/rfc/rfc2045)

The 7bit/8bit/binary transfer encodings are identity transformations, describing byte domains rather than charsets. Quoted-printable and base64 transform the body. Quoted-printable soft line breaks and escapes can alter the decoded text; base64 reconstructs another octet stream. A received 7bit/8bit label alone proves neither that the sender used that transformation nor that the body is UTF-8, especially when the label is unsigned.

**Inference:** unsigned CTE may be an admission restriction, but must never choose a different decoder. Relabelling a signed encoded body as 8bit cannot be allowed to trigger a permissive substring parser that interprets encoded text as a fresh payment. No QP/base64 fallback, guessing, recursive decoding or auto-detection belongs in this narrow candidate.

### Content-Type/charset can change interpretation without changing bytes

[RFC 2046 sections 4.1.1-4.1.3 and 5.1](https://www.rfc-editor.org/rfc/rfc2046)

Text charset maps octets to characters; plain text, HTML-like formats and multipart bodies have different processing rules. Multipart boundary parameters select the internal structure and parts. Plain text's MIME default charset is US-ASCII, not UTF-8. Therefore ignoring unsigned Content-Type is safe only with an explicitly fixed application interpretation; it is not ordinary MIME parsing with an implicit UTF-8 default.

[RFC 3629 sections 3-4 and 10](https://www.rfc-editor.org/rfc/rfc3629.html)

UTF-8 has a defined byte grammar; invalid, overlong and surrogate encodings require protection against misdecoding. Strict UTF-8 validation establishes validity under that decoder, not sender intent. Reject invalid sequences rather than replacing them, changing charset, dropping bytes or normalizing characters until they match financial identifiers.

## mailauth 8.0.4 contract observed

Installed file evidence:

- `dist/esm/dkim/dkim-verifier.js:384`: feeds body chunks to each registered hasher.
- `dist/esm/dkim/dkim-verifier.js:477`: selects the signature-associated hash; line 496 compares against its `bh=`.
- `dist/esm/dkim/dkim-verifier.js:603`: exposes `bodyHash`, `bodyHashExpecting`, signing headers and status; lines 615-630 expose canonical lengths and length-limit state.
- `dist/esm/dkim/verify.js:3`: wrapper returns result/header metadata, not canonical body bytes.
- `dist/esm/dkim/body/relaxed.js:101`: operates on byte values SP/HTAB/CR/LF; it also tolerates LF input. `setContentType()` affects MIME structure detection, not the body hash transformation.
- `dist/esm/dkim/body/index.js`: `BodyHashStream` forwards original input chunks; its stream output is **not** canonicalized body output.

Version-tagged upstream verification: [verifier source](https://raw.githubusercontent.com/postalsys/mailauth/v8.0.4/src/dkim/dkim-verifier.ts), [wrapper source](https://raw.githubusercontent.com/postalsys/mailauth/v8.0.4/src/dkim/verify.ts). Body implementation web fetches failed; claims about those modules above rely on the installed 8.0.4 files, not another release.

## Candidate application contract for independent review

This is a proposed restriction set, not approval to remove the existing signed-MIME requirement:

1. Preserve the current fully signed MIME profile. Add any narrow profile explicitly for the server-owned bank notification contract only; preserve every signer/sender, recipient ownership, time, algorithm, full-body, replay, parse, amount, reference and settlement guard.
2. Select one eligible passing signature and retain that exact result association. Never combine one signature's signer/pass with another's body hash or signed-header coverage. Keep `l=` absent; no body-prefix acceptance.
3. Extract body octets from the original message using the same proven header/body boundary as verification. Recompute only the selected proof's supported body canonicalization. Reject malformed/unsupported input, bare CR and preferably bare LF rather than relying on differing parser repairs. Bound raw and canonical sizes/lines.
4. Before interpretation, require SHA-256 of that exact projected byte buffer to equal **both** `bodyHash` and `bodyHashExpecting` from the selected passing result; cross-check canonical length. Fail closed on any mismatch. Hash equality is a consistency binding, not a substitute for signature verification.
5. Interpret that same buffer once through strict UTF-8 identity decoding and a fixed plaintext protocol. No unsigned header may select charset, CTE, HTML, multipart part, boundary, text/enriched, flowed wrapping, attachment or fallback parser. Received MIME controls can reject incompatible inputs only. A successful profile probe is necessary but does not authenticate those headers.
6. Positively recognize one complete bank notification grammar; require unique financial fields and known field relationships. Reject multipart/header fragments, encoded alternatives, arbitrary prefix/suffix matches and ambiguous duplicate payments. Do not concatenate digit tokens across whitespace or decode HTML entities. Require ASCII financial identifiers/numbers and exact supported separators. Keep any accepted non-ASCII label literals explicit. Reject NUL, unexpected controls, bidi/invisible characters and encoding BOM unless separately justified.
7. For relaxed mode, all accepted financial meaning must be invariant across its SP/HTAB/trailing-line equivalence. Treat a single canonical SP as a delimiter, not evidence of an authenticated exact spacing count. Maintain line boundaries. Replaying a whitespace-equivalent notification must map to the same credit identity and dedupe outcome.

## Unsolved risks and acceptance gate

- An unsigned 7bit/8bit header can be attacker-added/replaced. A fixed grammar and fixed decoder provide the defense; checking the header value alone does not.
- Valid UTF-8 does not rule out another intended charset or distinguish plain text from an encoded or multipart container. The actual bank profile and positive grammar still need qualification.
- The existing parser may depend on whitespace/charset/MIME decoding. This research did not inspect or approve it; full-message ambiguity and bank-signed non-transaction messages need adversarial cases.
- Reimplementing canonicalization can diverge at empty bodies, terminal whitespace, line endings, UTF-8 chunk splits or malformed input. Equality binding plus differential tests are required. No upstream function observed returns the canonical bytes directly.
- DKIM proves a signing domain's responsibility, not a successful bank settlement or replay freshness. Existing financial/reconciliation rules remain necessary.

Proceed only after the operator's safe profile probe, explicit scope authorization, synthetic positive fixtures and independent security/QA review. If actual CTE is QP/base64, or body grammar cannot be justified, this identity candidate remains blocked; do not broaden it speculatively.

## Exact checks/results

Read-only Node execution imported the installed 8.0.4 body hasher and ran **6 passing synthetic assertions**: canonical relaxed projection/hash agreement; relaxed SP/HTAB/trailing-line equivalence; distinction between spaced/unspaced digits; simple-mode spacing distinction; QP-wire versus decoded-text hash distinction; fatal invalid UTF-8 rejection. These checks cover a few explicit examples, not comprehensive canonicalization correctness or bank interoperability. No signing, DNS, bank mail, current bank key query, replay, production endpoint or payment was used. Only this assigned `/tmp` report was written; no repository/configuration/Git changes.
