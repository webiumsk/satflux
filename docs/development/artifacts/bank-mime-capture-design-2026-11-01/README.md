# Private MIME capture design review - 2026-11-01

Status at design freeze: accepted; owner implementation-scope approval was pending.
The owner subsequently approved implementation; see
[BANK_MIME_CAPTURE_IMPLEMENTATION.md](../../BANK_MIME_CAPTURE_IMPLEMENTATION.md). No runtime
implementation, activation, deployment or real bank-content capture occurred.

Proposal: [BANK_MIME_CAPTURE_PROPOSAL.md](../../BANK_MIME_CAPTURE_PROPOSAL.md).
Frozen SHA256: ba1177a5867ddae9773499091ffd540cdd51be921cfe85b3ccb28d19b669b905.
Runtime source remains clean at037a6fa7 in its retained worktree; unrelated primary
changes are preserved.

- Independent [security design review](security.md), /root/bank_mime_diagnostic_security:
  initial one-shot ambiguity fixed; amended frozen design accepted, no remaining blockers.
- Independent [QA design review](qa.md), /root/bank_mime_diagnostic_qa:
  four contract clarifications closed; amended design accepted, future tests specified.
- Independent [signature semantics audit](signature-semantics.md): 18 synthetic
  assertions demonstrate crypto PASS can still fail the complete proof predicate.
  This is not an actual-bank rejection diagnosis.
- Primary-source [recovery research](recovery-research.md): Free retention is not
  established; logs cannot substitute for original MIME. No provider ticket or
  bank request was sent and no external account/configuration was changed.

No implementation tests exist or passed for the proposed capture. Future approval
must preserve default-off, explicit one-Store arming, existing-slot atomic capture,
immutable two-hour deadline, missing-state failure, whole-envelope encryption,
1MiB cap, fixed diagnostics, all-worker compatibility and backup-residual consent.
Neither design acceptance nor diagnostic output authorizes payment confirmation.
