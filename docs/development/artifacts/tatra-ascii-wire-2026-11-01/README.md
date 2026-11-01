# Tatra ASCII wire-profile review - 2026-11-01

Base 406e1fef; local branch `fix/tatra-ascii-wire-profile`. This is a reviewed
follow-up to the capture work in PR #415, not a change to capture retention or a
financial replay mechanism. The owner supplied only safe signature/format facts;
agents did not access the original bank message, account, amount or reference.

The observed message has an aligned passing bank signature covering the entire
body and From/To/Subject, but not MIME controls. The narrow fallback admits only
plain UTF-8/7bit/MIME1.0, bounded ASCII and relaxed/relaxed. Its fixed canonical
wire-body projection must match both hashes and both lengths of one same valid
bank proof. MIME headers cannot select decoding. Legacy fully signed MIME keeps
precedence, result shape and body; no other proof or financial guard changes.

| Gate | Result and attribution |
| --- | --- |
| Node author | [Report](node.md): 21 new/77 total cases pass, zero skips; original legacy predicate, CLIs, locks and existing tests unchanged |
| Main PHP author | 91 cases/991 assertions pass (capture/normal MIME flow), scoped Pint (five files) and full unchanged-baseline PHPStan pass |
| Independent QA | [Report](qa.md), /root/bank_mime_diagnostic_qa: 256 repository PHP/1550 assertions,19 private PHP/197 assertions,77Node and128-body/666-check independent projection model pass, zero skips; source/API/docs accepted without findings |
| Independent security | [Report](security.md), /root/bank_mime_diagnostic_security: 91 PHP tests/991 assertions, 6011 frozen-helper differential/boundary checks and 38 real-crypto checks pass; frozen source accepted without findings |
| Main integration | Current API export/check and offline OpenAPI validation pass; source/preserved manifests and diff checks pass |

Design evidence is separate from final candidate execution: [security](architecture-security.md),
[QA](architecture-qa.md) and [standards research](standards-research.md) establish why
blindly dropping MIME signature requirements is unsafe and constrain the fix.
The [nine-file source manifest](source.sha256) identifies the final reviewed
runtime/tests/schema/exporter. The [18 preserved files](preserved.sha256) match
the base, including strict financial parsers, dedup, reporting, ingress/jobs,
owner handling, CLI transports, Company runtime, configuration and dependency locks.
Legacy predicate equivalence is additionally checked within the changed verifier.

The normal synthetic flow reaches the existing contract-v2 provider stub. Full
MIME, ASCII fallback and equivalent SP/HTAB forms retain exactly the same account,
amount/currency/reference and credit/dedup identity. Encoded/nested/tampered
variants cannot report a credit. Operator diagnosis adds fixed body_profile and
transaction_profile facts only; original v1 capture/expiry/ciphertext are unchanged
and no import, report, queue dispatch or financial audit mutation occurs.

Targets are synthetic SQLite memory, array stores, sync queue, null logs, existing
locked dependencies, fake DNS and disposable Redis 7 without external network or
persistence. Reviewers mount source read-only and use separate Redis prefixes.
No production wallet, key, database, real message, funds or payment API is used.
Machine-specific checkout prefixes in copied reports are normalized.

Fresh exact-head CI/Node 22 is a separate publication gate; local Node 24 passes
are not remote CI evidence. Actual retained bank-body frame qualification is
still required through a read-only compatible recheck while the capture lives.
`transaction_profile=matched` establishes shape, not invoice matching, provider
acceptance or settlement. Old consumed payments are not automatically replayed.

See [the implementation contract](../../TATRA_ASCII_WIRE_PROFILE.md).
