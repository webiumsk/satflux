# Bank MIME rejection diagnostics - 2026-11-01

Baseline: PR414 `cc3967abfb35f826af91858dff771fa999087a2c`.
This follow-up adds diagnostic metadata only. Existing Store rejection reason
`unverified_bank_mime` is preserved; the new `verifier_reason` is restricted to
the 15 existing permanent runtime codes, a fixed `unknown` fallback or the local
pre-verifier `invalid_utf8`. No arbitrary process data is retained.

The owner reports the open branch deployed. Owner-run diagnostics show UTC,
enabled bank inbound, an empty pending webhook queue and a MIME rejection at
09:00:32 UTC, two seconds after the Mailgun HTTP delivery event. Exact deployed
revision is not independently verified. This narrows this message to a permanent
verification rejection, without identifying its subtype. Older DNS failures do
not establish the cause. No raw bank message or merchant identifier is retained
in this evidence.

| Gate | Evidence |
| --- | --- |
| Author | 74 PHP cases / 442 assertions, full existing PHPStan and scoped Pint pass |
| Independent security | `/root/bank_mime_diagnostic_security`: accepted frozen four-file diff; 79 private synthetic assertions, exact runtime reason allowlist, seven unchanged trust-boundary files |
| Independent QA | `/root/bank_mime_diagnostic_qa`: accepted frozen source; 189 repository PHP cases / 792 assertions, 40 private cases / 312 assertions and 42 Node cases pass, zero skips |
| Main | Four-file diff check and eight-file preserved baseline manifest pass |

The [author](backend.md), [QA](qa.md) and [security](security.md) reports record
separate evidence and limitations. The [source manifest](source.sha256) covers the reviewed runtime service, tests
and two documentation files. The earlier eight-file preserved manifest remains
valid. Node runtime/locks, transaction parser, deduplication and importer are
unchanged. Status classification, ciphertext, recipient/owner binding, Company
policy, retries and all payment reporting behavior remain unchanged.

Author and reviewers use synthetic isolated targets, existing locked dependencies,
network isolation and no production database, wallet, message, credentials or
transfer. Application test configuration is verified before execution. Local
Node24 crypto coverage and prior Node22 CI are separate from fresh CI on a new
commit; prior green CI is not claimed as this follow-up's result.

Reviewing this metadata does not qualify actual current-bank signing or layout.
Handled rejection jobs do not retain original MIME and the old audit cannot be
backfilled. A privately available original bank EML is needed for actual failure
qualification. No forced-paid change, reconstructed signed message, replay,
additional real transfer, merge, deployment or route edit is part of this work.
