# Private bank MIME capture implementation

The owner approved implementation on 2026-11-01 after independent design review
of proposal `ba1177a5`. The [frozen proposal](BANK_MIME_CAPTURE_PROPOSAL.md) and
[design reviews](artifacts/bank-mime-capture-design-2026-11-01/README.md) describe
that historical approval point. Capture remains disabled by default; preparing
source does not activate it, deploy it or confirm any payment.

## Acceptance and integration contract

One explicit operator activation selects an existing local Store and a maximum
120-minute window. Redis server time defines the immutable absolute deadline.
The only event transition is an atomic compare-and-swap of that existing,
unexpired encrypted ARMED slot to CAPTURED, retaining exactly its original expiry.
Missing/evicted state never automatically re-arms. Exactly one global slot is
allowed; a new operator activation cannot overwrite an unexpired one.

The complete envelope authenticates the schema, owner, generation, deadline,
original MIME, recipient, original diagnostic summary and exact-content digest.
Raw MIME remains bounded to256KiB; the serialized encrypted record is capped
at1MiB. Redis/cache failure never falls back to a file or changes the existing
verification decision, rejection or retry classification.

Capture does not select or relax bank-proof policy. The separately reviewed
[ASCII wire-body contract](TATRA_ASCII_WIRE_PROFILE.md) documents the current
additional profile while preserving the legacy fully signed MIME path. A separate
private diagnostic entrypoint shares the same verification evaluation and returns
`{verification, diagnostics, contentSha256}` over stdin/stdout to PHP memory.
PHP requires the digest to match its exact raw input. Operator commands never
emit the verification payload or digest, only a validated diagnostic summary:

```text
version: 1
status: verified | invalid | retryable
reason: fixed allowlisted code
signatures: at most 5 entries
  verdict: pass | fail | neutral | policy | none | permerror | temperror | unknown | null
  bank_signer, bank_aligned, algorithm_allowed, time_valid, full_body: boolean | null
  headers: exactly from, to, subject, mime-version, content-type, content-transfer-encoding
    required, signed: boolean | null
```

Null means unavailable/not evaluated rather than a failed check. No domain,
selector, key, arbitrary header/comment, body, subject, address or signature is
emitted. Original and current recheck summaries are labeled separately: key
rotation, expiration or temporary DNS problems can change a later result. Neither
summary is payment evidence by itself. The command does not invoke intake,
transaction import, audit-as-paid, provider reporting or financial replay.

The outer `body_profile` is a fixed recognized profile label. The outer
`transaction_profile` is matched/unsupported/not_evaluated from a pure strict
credit-frame check of the bounded verified recheck body. It discards all parsed
values and cannot establish invoice/account matching or settlement. Existing
version1 originals, ciphertext and deadlines remain unchanged.

Normal disabled/unarmed verification uses the existing entrypoint. Diagnostic
transport failure falls back to the existing strict verification path; capture
failure cannot turn any result into paid. Company importing is unchanged.

## Ownership and validation

| Owner | Exclusive writable scope |
| --- | --- |
| Backend author | Capture service, PHP diagnostic runner, Store verifier integration, capture config, three Artisan commands and capture/summary PHP tests |
| Node author | Shared verifier diagnostic projection, private diagnostic CLI and its dedicated Node tests |
| Main | Environment example, CI disposable Redis fixture, generated API artifacts, documentation, manifests, integration and Git publication |
| Independent security/QA | Read-only reviewed source; private synthetic probes and reports only |

Base source037a6fa7 depends on the existing single-route PR414. The separate
feature branch preserves both that reviewed branch and the unrelated dirty
primary checkout. Provider repositories, frontend, billing, routes and financial
history remain outside this change.

Local test targets use SQLite memory, array cache/session/mail, sync queues,
null logs, synthetic app/provider keys and existing locked dependencies (168/168
Composer versions/references checked). A disposable Redis7 server has no external
network or persistence; test PHP containers share only its loopback namespace.
Explicit `BANK_MAIL_CAPTURE_TEST_REDIS=1` and a dedicated prefix protect the Redis
integration suite. CI adds an ephemeral Redis service and runs those tests; it
does not activate capture or change production infrastructure.

Require real Redis concurrency/expiry/eviction tests, default-off/no-capture,
owner isolation, exact encrypted bounds, hostile summaries, safe CLI errors,
same-evaluation verifier equivalence and existing bank-mail regression. Independent
source review and fresh exact-head CI remain required before rollout.

## Operator rollout and diagnostics

Every worker consuming the webhook queue must run the compatible revision and
locked runtime dependencies. Redis7 or newer is required for absolute-expiry
inspection; local and CI integration checks use Redis7. Verify the actual selected
connection before activation rather than assuming the server image is current.
Keep the existing single EU `.mime` destination.
Configure the effective selected Node executable as documented in the existing
runbook. Set `BANK_MAIL_CAPTURE_ENABLED=true` only for an approved diagnostic
window, rebuild configuration and reload compatible workers through the normal
deployment procedure. The default Redis connection is `cache`; an existing
private connection may be selected using `BANK_MAIL_CAPTURE_REDIS_CONNECTION`.

Live TTL does not erase old Redis persistence files or backups. Their encrypted
residuals retain the existing infrastructure retention and key protections. The
arm command requires explicit acknowledgement of that limitation:

```sh
php artisan bank-mail:capture-arm '<LOCAL_STORE_UUID>' --minutes=120 --acknowledge-backup-retention
php artisan bank-mail:capture-status
php artisan bank-mail:capture-diagnose
```

Run inside the actual queue container. The Store UUID stays on the server; do not
send it or any bank content to support chat. Status and diagnosis emit only safe
JSON. Read-only diagnosis may inspect an unexpired capture while writing is
disabled and never extends its deadline. Disabling does not flush queues or
financial history. The deadline expires automatically; no new transfer is a
diagnostic prerequisite and this feature cannot recover a prior consumed email.

Independent implementation reviews and local checks pass; the exact results and
limits are in [the evidence artifact](artifacts/bank-mime-capture-implementation-2026-11-01/README.md).
Fresh exact-head CI and actual deployment/activation remain separate gates.
