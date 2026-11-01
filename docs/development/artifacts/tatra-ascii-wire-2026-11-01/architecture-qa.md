# Unsigned MIME controls: independent architecture QA

Reviewer: /root/bank_mime_diagnostic_qa. Date: 2026-11-01.
Final disposition: DESIGN QA ACK for the narrowed operator-confirmed ASCII/text/plain/UTF8/7bit/MIME1.0, relaxed/relaxed-only fallback below. Supplied operator format facts resolve the initial format-dependency hold. This is architecture QA acceptance for main coordination, not implementation/security/runtime approval; the fully signed legacy profile remains intact and signed-MIME checks must not simply be removed around mailparser.

## Repository, scope and provenance

webiumsk/satflux; clean feat/bank-mime-private-capture at406e1fefb9840d1ac851a7fdbf337f84eba0b645. PR414/415 publication/open state and owner-run deployment are separate facts supplied by main; no merge or deployed SHA was inferred. Source unchanged throughout review. Primary unrelated dirty work preserved. Graph absent in scoped worktree; main's Graphify query was stale/irrelevant. Relevant root/state and architecture-review/integration-testing instructions read. Report/probes written only under /tmp; no repo/Git/package changes.

Reviewed source hashes:

| File | SHA256 |
|---|---|
| TatraBankSepaEvidenceParser.php | eb62a9b13eb72699db7ad49ca00bcfcf7a0a5fe0b1270f193f62d91e8def85eb |
| TatraBankEmailParser.php | 67738444c44b769389a34a5dd4fe9129596e63f27875451b54ce848d87cb65c1 |
| SepaEmailConfirmationService.php | 34045bf33ec5c3677dc953742e84541df85650dffd3ac6a3f1fdad1b2b5ddb8a |
| runtime verifier.mjs | 0a1ea5b71bd8903af8983253abd8203bfbf4f38ffd83c3dab70107c0f25c99da |

Actual-capture diagnostic facts supplied by main: sole DKIM PASS, bank signer/alignment/algo/time/fullbody true; signed From/To/Subject, three unsigned MIME controls. Later operator format facts supplied by main: text/plain, charsetutf-8, CTE7bit, canonicalizationrelaxed/relaxed, wireUTF8true, ASCIItrue, controlsfalse. No actual original bank MIME/private values were accessed or independently inspected. These facts establish the bounded format target for this design; complete transaction layout still needs the pure local structural status.

## Verified parser boundary

TatraBankSepaEvidenceParser.php:24-40 uses complete input, valid UTF8,65535byte cap, C0/DEL/Cf/markup/entity denial,10-16nonblank lines, exact greeting and full-line date/account/credit header. :43-69 validates calendar/time/checksummed account, positive EUR amount, ordered three balances, numeric CCINT/CSO account frame and anchored QR32hex or numeric VS/SS/KS. :81-95 requires bounded tail and retains reference-independent credit identity. :99-135 admits only enumerated post-reference fields and rejects embedded structural labels; it never extracts a financial frame from arbitrary metadata.

TatraBankEmailParser.php:27-60 is a separate permissive importer: it normalizes HTML/entities/WSP and substring-matches a classic header, or falls back to compact heuristics. It attaches automatic E2E/explicitVS/credit identity only from the strict evidence parser, selected by bank sender. SepaEmailConfirmationService.php:51-55,:137-153 requires that bounded reference evidence before reporting. The importer alone is not a safe acceptance gate for a new verification profile. Synthetic unsupported bodies can still produce ordinary parsed rows without financial reference evidence; that is existing compatibility behavior, not a verified financial exploit.

## Executed synthetic evidence

Existing pinned dependencies independently identified: mailauth8.0.4, mailparser3.14.1. Inspected package canonicalizer/verifier source, including result.bodyHash, bodyHashExpecting, format, canonBodyLength/Total/Limited. No dependencies installed. The package's exported alias paths map to dist; an initial targeted read of nonexistent lib disk paths was corrected to the existing dist paths, with no mutation.

QA runner `/tmp/bank-unsigned-mime-qa/run.sh` derives from previously inspected synthetic runner, with --network none, /app source read-only, public.env.example masked over.env, existing vendor/Node dependencies read-only and probes read-only. Redis fixture remains stopped and is not used/restarted. PHP environment is testing/uncached/SQLite memory, array/null drivers, prevented stray HTTP; no DB queries/writes or financial handler invocation by probes. Node DNS is an injected in-process public synthetic fixture. No actual bank message, merchant token, funds, wallet, production service or external network.

1. `/tmp/bank-unsigned-mime-qa/run.sh php /tmp/bank-unsigned-mime-qa/parser-probes.php`

PASS18cases/54explicit checks. Baseline strict frame and downstream tuple valid. Three relaxed-equivalent variants (multiple SP, SP-to-HTAB, trailing SP/HTAB plus empty trailing lines) are rejected as raw strict input but canonicalize to exactly the same accepted body, amount/account/E2E/credit identity. Fourteen other variants reject strict evidence before and after projection: prefixed outer text, two bank frames, nested credit/remittance reference, duplicate/suffixed reference, numeric amount/account splice, QP-encoded reference, entity injection, NUL/VT/bidi and NBSP numeric grouping. All retain null E2E/false explicitVS/null credit identity even though permissive importer returns one row. This is a model canonicalizer under /tmp, not production candidate code.

2. `/tmp/bank-unsigned-mime-qa/run.sh node /tmp/bank-unsigned-mime-qa/crypto-probes.mjs`

PASS2real-crypto canonicalization scenarios/38explicit checks (relaxed and simple). Synthetic bank signatures sign From/To/Subject but omit MIME controls. Crypto passes; current strict runtime correctly returns invalid_signature. Independent canonical-body SHA256 matches both bodyHash and bodyHashExpecting from that exact proof; projected byte length equals canonBodyLength and canonBodyLengthTotal, with no l= limit. Relaxed SP/HTAB/trailingWSP variants preserve valid crypto and identical projection; simple does not tolerate an internal SP-to-HTAB change. Trailing empty CRLF equivalence preserves both modes. A changed financial amount loses crypto validity.

Most consequential counterexample: changing unsigned CTE8bit to quoted-printable preserves DKIM PASS and exact body hashes but changes mailparser.text for a synthetic literal=41 body. Fixed canonical raw-body projection remains unchanged. Therefore preserving body-hash validation but returning mailparser.text under unsigned MIME controls is unsafe as a general design. The experiment changes only synthetic note text; it is an interpretation counterexample, not a claim of a current-bank payment exploit.

No full regression suite or production candidate was run: implementation is not authorized by this design assignment. Counts above describe explicit probe checks, not PHPUnit tests or final feature acceptance.

## Final narrowed contract ACK after operator format facts

The final proposed implementation target is narrower than the initial multi-mode exploration below:

- Existing fully signed legacy path has precedence and unchanged result/decoding/policy. The fallback admits only explicit text/plain, UTF8,7bit,MIME1.0 with validated ASCII wire body and valid UTF8. No8bit/simple-mode/default-encoding extension is part of this candidate.
- Only one otherwise fully eligible aligned bank proof with signed From/To/Subject and exact relaxed/relaxed mode can supply the fallback. Whole-body/no-l=, algorithm, time, DNS limits, header singleton/CRLF, envelope recipient, origin and financial guards remain.
- Independent RFC-relaxed canonicalization over the raw wire body is hash-bound to both hash fields and both exact canonical lengths from that same proof. Raw SP/HTAB is explicitly allowed only as relaxed WSP; the projected body contains canonical SP and is the exact body used downstream. No MIME decoder/part/charset/header selection supplies text.
- Unsigned admission-control mutation can only reject or preserve identical authenticated interpretation; it cannot select another decoder. Non-ASCII/control/Cf/entity/unsupported complete frames remain denied by admission/strict evidence as applicable.
- Recheck diagnosis may add only a fixed outer transaction_profile status computed by the pure TatraBankSepaEvidenceParser over the exact successfully recheck-verified body. Retry/invalid/unavailable rechecks produce an explicit not_verified/unknown fixed outcome, never a supported-profile claim. Original and current summaries remain separate. No importer, financial job/provider call, raw field, account/reference/amount or payment-state output is allowed.

Minimum critical source acceptance tests: same relaxed projection and full financial tuple for SP/HTAB/trailingWSP variants; mutated unsigned CTE/charset/contenttype/MIME-version denied or identical projection; missing/unsupported controls denied in fallback; only relaxed/relaxed fallback; same-proof multi-signature/hash/length/no-l= guards; body/amount mutation loses trust; old fully signed QP/base64/simple behavior unchanged; same credit identity across old/new rendering and no financial duplicate; pure CLI status for verified-supported, verified-unsupported, invalid, retry, expired/corrupt capture without financial side effects or private output. Diagnostic header-required facts must describe the active profile consistently without rewriting original stored summaries.

No new design QA blocker remains for main's bounded implementation planning. An eventual implementation still requires new frozen-source independent QA/security review and CI. None of the synthetic model results below is a pass for that future source.

## Initial generic contract, constrained by final narrowed ACK above

1. Preserve the fully signed MIME profile, its QP/base64/charset behavior and verification/diagnostic/retry outputs unchanged. The new profile is separate and has a fixed byte interpretation. It must never authorize proof while passing MIME-selected/decoded mailparser text as its body.
2. Select one eligible proof whose own crypto, bank signer/alignment, algorithm, time, complete body and signed From/To/Subject all pass. Recompute projection/hash from that same proof's canonicalization. Do not combine fields, lengths, body hash or header coverage from separate signatures. Reject all l= forms, including equal/full apparent length.
3. Split the original message only at its validated first CRLFCRLF wire separator. Keep existing size, CRLF, singleton identity/control, sender/recipient and no-forward/ownership gates. No multipart part selection, MIME boundary/header re-selection, charset decode, QP/base64 decode, HTML stripping, entity decode or substring extraction supplies authenticated text in this profile.
4. Admission permits only an explicit finite text/plain UTF8/ASCII and7bit/8bit set supported by the actual format evidence. Unsigned controls can deny admission but cannot choose interpretation. Missing CTE/charset/MIME-version defaults must be explicitly frozen or denied, not library defaults. Reject highbit data under a7bit/ASCII declaration. Unsupported QP/base64, charset, HTML/multipart, disposition and unknown canonicalization stay fail closed in the new profile.
5. Canonicalize bytes, not Unicode whitespace: relaxed reduces SP/HTAB sequences, removes line-final SP/HTAB, preserves internal CRLF and removes trailing empty lines; simple keeps internal WSP. Do not use generic whitespace regexes that collapse NBSP, Unicode separators or line boundaries. Do not NFC/NFKC or case-fold body/account/reference. The exact canonicalization identifier comes from the selected proof; unknown or absent handling must be explicit. Simple-empty and relaxed-empty edge cases need separate tests even though strict transaction parsing rejects an empty body.
6. Independently compute SHA256 of the complete projected bytes, require equality to both selected proof.bodyHash and bodyHashExpecting, require finite exact canonical length equality to both length fields and no limited body. Raw sourceBodyLength is not interchangeable with canonical length. Unknown/malformed hash/length/format fails closed. Both original-byte hash and parsed financial identity retain their existing roles; no library-provided canonical text is trusted without that independent binding.
7. Validate projection UTF8 and bounded length, reject controls/Cf/NUL/DEL/HTML/entities as existing strict evidence requires. Explicitly choose raw HTAB policy: reject it outright for this profile, or allow only as DKIM WSP before relaxed projection, then ensure none reaches strict parser. Current raw strict parser rejects HTAB; quietly parsing raw and projected variants differently would make acceptance dependent on unauthenticated equivalent rendering.
8. Feed the exact same authenticated projection to strict evidence and downstream row parsing. A successful new-profile body must be a complete anchored supported frame, not a matching substring/compact row. The reported amount, currency, account, reference and conservative credit identity must match that frame; no subject/free-note/decoded metadata can override it. Keep legacy Company behavior separate.
9. Preserve credit identity schema and provider contract/owner/idempotency/retry guards. The same financial credit represented through old signed-MIME and new canonical profile must retain the same invoice-independent credit identity, avoiding a new payment identity per rendering/profile/hash. Unknown outcomes stay unknown; no replay of captured historical mail is part of qualification.

## Minimum follow-up contract tests

| Area | Required cases |
|---|---|
| Two-profile compatibility | Existing fully signed positive/negative/QP/base64 tests unchanged; new-profile7bit/8bit positives; unsupported encodings never fallback to decoded content |
| Exact proof | Two signatures with complementary failed checks; hash/length/canonicalization taken from wrong proof; malformed/missing fields; every l= form denied |
| Canonical invariance | Same relaxed canonical bytes across SP/HTAB/trailingWSP/empty tails => same disposition and full financial tuple; simple internal changes cannot inherit proof |
| Wire grammar | CRLF versus bareCR/LF, folded/duplicate controls and identities, missing controls/defaults, multipart/HTML/disposition, all body/line/byte limits |
| Interpretation | Unsigned CTE/charset/contenttype mutation either denied or same projection; literal=xx/base64-looking/HTML/entity/Unicode bytes never decoded into financial evidence |
| Structural evidence | Prefix/suffix/second frame, duplicate/free-field reference, numeric account/amount splices, invalid date/account/currency/zero/debit, reordered balances, long/unknown tail |
| Tuple/identity | E2E case preservation, leading-zero numeric symbol policy unchanged, amount/account/reference exact match, profile-independent credit identity/once-only provider consumption |
| Privacy/recovery | Safe fixed rejection reason/profile codes, diagnostic status parity, timeout/DNS retry, no bank data in output/errors, capture command remains nonfinancial |

## Remaining qualification boundary

The initial format-dependency blocker is resolved by operator-supplied allowlisted CTE/charset/canonicalization/UTF8/control facts from the existing capture, as recorded above. These were supplied by main, not independently accessed by this reviewer. No repeated new transfer is required and no raw EML should be uploaded to this agent/chat.

Those format facts alone also do not prove the entire current Tatra transaction layout matches the historical strict frame. Before claiming end-to-end current-bank coverage, a local private structural check can return only fixed match/row-count/reference-evidence booleans; never extract or print merchant fields. If the frame differs, stop and review a separately bounded parser profile instead of weakening anchoring or inferring arbitrary note values.

No current parser vulnerability was verified. No counterexample found within the tested full canonical-projection + exact hash/length + existing strict-frame model that changed amount/account/reference while preserving projected bytes. This bounded result is not a proof for all messages or an approval of unimplemented source. Independent security review and new frozen-source tests remain required for any eventual implementation.
