> Historical evidence for initial revision `12e00fa4`, superseded by the
> [final follow-up](../bank-mail-single-route-review-2026-11-01/README.md).
> That candidate included optional compact-date normalization, now withdrawn.
> Its manifests/commands apply only to a disposable archive of `12e00fa4`,
> not the current source. Initial CI passed, but did not dismiss the review risk.

> Archived review evidence. Private fixture/runner paths below are redacted;
> they are not committed runnable tests. Use the repository commands in README.md.

# Independent security review - one bank MIME route

Reviewer: `/root/wallet_bmail_security`.
Implementation author: `/root/wallet_drift_backend`; API/documentation owner: main.
Repository: Satflux / `webiumsk/satflux`.
Base: `c90caa40e048cdf2b740e0195fd7198bd5209fe7`.
Reviewed branch/worktree: `fix/bank-mail-single-route` / ignored named worktree.
Disposition: **ACCEPTED for the exact frozen 12-file patch** recorded in
`FROZEN_SHA256SUMS`. No unresolved blocking security findings. Later material
changes require renewed review. Main-owned API/documentation hashes at review
are recorded separately in `MAIN_DOCS_SHA256SUMS`; final count/status edits remain
main's responsibility. No merge/deployment or payment approval is granted here.

## Scope and independence

Architecture review preceded implementation; see `architecture-review.md` for
source evidence, trust matrix, risks, proposed ownership and negative test plan.
The owner then authorized the bounded existing bank-mail repair through main.
Scope includes server-side owner dispatch, a separate Company MIME adapter,
encrypted optional envelope metadata, typed transient result, private Company
logging and the explicitly authorized compact-date normalization.

Reviewer authored only private synthetic tests/reports under
`<private-bank-single-route-security>`; no product code. No repository/source/Git,
configuration, production API/database, bank message/credential, wallet, real
funds, package or deployment changes were made by reviewer. No screenshot private
values or original messages were inspected. Source tests and approved deployment
remain distinct; real bank signing/layout and settlement are unverified.

## Verified security properties

1. Policy derives from persisted envelope-recipient owner, not request mode,
   model ID, MIME To, or supplied proof fields. BankMimeVerificationService:22-36
   dispatches Store to existing strict verification and Company to separate
   extraction. Invalid Store proof has no Company fallback. The transient
   PreparedBankMimePayload carries the original owner; BankInboundEmailService
   rechecks exact class and local ID before parsing/import/reporting.
2. Company child output uses `extracted`, never `verified`, and a raw-byte digest.
   The PHP adapter requires expected field types, raw digest, UTF8 and byte bounds,
   then explicitly copies only to/from/subject/body/header facts. Child/request
   bankDomain, verified markers, claimed owner and mode cannot escape this branch.
   No Company message gains SEPA report authority, even when its MIME has a bank
   signature or arbitrary Authentication-Results headers.
3. Original MIME and optional envelope sender remain separately encrypted in the
   serialized job. Decoded content stays local. Company uses the existing envelope
   sender priority with header-From fallback for older jobs. Store continues using
   signed MIME From; the optional envelope sender does not replace that proof.
   Company routing remains envelope-based, including legitimate BCC/header-To
   differences, while strict Store signed-recipient checks remain unchanged.
4. Only forwarding marker facts leave the Company subprocess. Existing Company
   forwarding rejection remains available without exposing raw header values.
   A literal server-only privateMime argument suppresses decoded From/Subject in
   Company no-row diagnostics. Error/retry/failed hooks retain ciphertext and
   replace private downstream exceptions without a previous exception.
5. Company MIME has binary-safe canonical base64 transport and the existing locked
   mailparser dependency. Raw 256 KiB, decoded body 65,535 UTF8 bytes, bounded
   subject/from/headers, CLI stdin/output and 15-second external process limits
   remain enforced. Locked mailsplit limits MIME children; locked mailparser has
   HTML nesting/conversion bounds. No attachment persistence, archive extraction,
   sender-chosen network target or DNS is added to Company extraction.
6. Strict Store cli.mjs/verifier.mjs are byte-identical to base. Diff checks also
   confirm SepaEmailConfirmationService, BankEmailOriginVerifier and strict Tatra
   evidence parser are unchanged. Existing DKIM/recipient/account/positive-credit/
   reference/contract/dedup guards retain their authority. No Sanctum/CSRF, session,
   entitlement/billing, encryption-key, wallet or local-first behavior is changed.

## Findings addressed during review

- Medium integration/privacy risk, resolved: direct Company reuse of Store-only
  MIME adapter would reject Company messages, and downstream no-row logging would
  expose decoded From/Subject. Separate extraction and private raw diagnostics
  now address both; no-row privacy also has author/independent QA coverage.
- High conditional cross-policy risk, prevented: no weak Company fallback for
  Store or client-selected owner/proof is present. Typed owner pinning and final
  exact class/ID checks prevent decoded content switching owner policies.
- Decoder qualification, resolved without broadening bank trust: the initial root
  UTF7 negative case exposed mailparser's safe literal fallback outside the custom
  charset hook. Explicit root charset validation now passes; nested transfer or
  refused-charset behavior remains inherited permissive **Company** behavior and
  is candidly qualified rather than represented as bank verification. No Store
  proof relaxation or new custom MIME-tree dependency was introduced.

No exploitable authentication, tenant escalation, secret disclosure or false SEPA
settlement was found in the final frozen patch. The preceding items distinguish
design risks and corrected integration issues from demonstrated financial attacks.

## Compact-date normalization and limits

The authorized TatraBankEmailParser delta changes only compact observed-date
parsing from `d.m.Y` to `!d.m.Y`: date-only input becomes midnight instead of
inheriting the processing clock. Cross-clock tests retain false explicit-credit
and reference-proof flags and null strict credit identity. Classic/strict proof
behavior and the dedup algorithm/history are unchanged.

Historical compact rows keep their old processing-time component; replay may need
manual reconciliation. Messages without observed date still use processing time.
Distinct same-day compact events with identical remaining dedup fields and no
bank transaction ID can conservatively collide. No universal or retrospective
idempotence, historic migration, balance repair or new bank timestamp is claimed.
Main's design/runbook now states these limitations.

## Independent tests and exact results

Tests executed against a reviewer-owned `git archive` of base plus exactly the
frozen 12-file overlay, not a writable application worktree. Existing locked vendor
and Node dependencies were read-only mounts. Resolved preflight confirmed testing,
SQLite `:memory:`, array cache/session/mail, sync queue, synthetic BTCPay URL/key,
no .env/config cache; every container had network disabled. Runtime PHP 8.3.31 /
PHPUnit 11.5.50; existing Node runtime. No application-source files were written.

| Independent execution | Result |
| --- | --- |
| Private SecuritySingleMimeBoundaryTest.php | **10 tests / 61 assertions PASS** |
| Existing unchanged strict verifier suite + new Company suite | **42 tests PASS, zero skips** (26 strict + 16 Company) |
| Private company-security-probes.mjs | **12 probes PASS** |
| Frozen author manifest before/after tests | **12/12 hashes match** |
| Strict CLI/verifier and unchanged proof-service diff against base | **empty / PASS** |
| Diff whitespace check | **PASS** |

Private PHP checks cover injected child/request proof/mode/owner claims, seven
malformed child outputs (digest/status/schema/UTF8/size), invalid Store verification
never invoking Company extraction, and Company downstream failure/failed-hook
privacy with immutable encrypted job state. Logs: `private-php.log`.

Private Node probes cover proof-marker absence despite forged auth headers, unknown
input keys, invalid base64, NUL/invalidUTF8/unknown root charset, deep/oversize HTML,
more than 1000 MIME parts, forwarding facts without header PII and local HTML
rendering. Logs: `private-node.log`; combined Node suite: `node42.log`.

The first private PHP fixture aborted because its helper name `result` conflicted
with PHPUnit's final method. Only that private helper was renamed; the unmodified
frozen product then passed 10/61. No failed product assertion was suppressed.

Author-reported independent gate inputs, not claimed as reviewer reruns: affected
PHP six-suite run **144/521 PASS**, Pint **9 PHP files PASS**, full unchanged-baseline
PHPStan **No errors**, strict file diff and author 42 Node cases pass. QA owns
separate real Company import/tenant/owner-change/charset/native compatibility
fixtures and will provide its independent final result to main.

## API/docs and operational gates

Reviewed API source/generated webhooks and route coverage, design note, ingress
runbook and prepended project-state/roadmap/decision entries. They accurately
separate Mailgun timestamp/token HMAC from bank proof, company local Paid matching
from SEPA authority, accepted HTTP intake from asynchronous outcome and one-route
compatibility from actual bank interoperability. Byte/text limits and compact
date/history/collision limitations are stated. Worker-first rollout and coordinated
rollback account for pending Company MIME jobs; the old parsed/native receiver
remains compatible. No new UI/locale string validation is claimed by this review.

Deploy compatible web and queue workers before any separately approved route
change. Do not downgrade workers while unsupported Company MIME jobs are pending;
coordinate rollback to the parsed catch-all to preserve company imports. Keep
Company imports at their existing trust level: they may mark local business
documents Paid but do not prove independently authenticated bank credits.

Unresolved operational evidence: actual current Tatra original MIME, signing/layout
interoperability, active deployed consumer/worker/provider revisions, route outcome
and the historical QR payment's settlement. Earlier retrieval 404s do not prove
expiration or payment. No new real transfer, forced-paid action, verified marker,
message reconstruction/replay or historical data rewrite follows from acceptance.
