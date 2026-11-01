# Incomplete SEPA credit evidence diagnostics

## Runtime evidence and limit

The owner reports a new notification at 2026-11-01 20:15:06 UTC with
`sepa.bmail_review_required`, `incomplete_credit_evidence`, and no verifier reason.
At base 12f83c11 this means at least one parsed credit, a passing sender/origin
gate and no credit satisfying all five existing eligibility predicates. This
branch returns before settings lookup or payment reporting. The audit does not
identify the selected body profile or the exact failed evidence condition.

The owner has only Mailgun logs; the previous private capture is empty. Store
processing does not persist these parsed rows or completed-job bank content.
Consequently the historical failure cannot be reconstructed from this audit.
No current bank template, missing field or QR-reference loss is assumed.

## Accepted diagnostic contract

The normal eligibility filter, provider calls, public result and review reason
remain unchanged. Only the existing incomplete-evidence audit gains a nested
`credit_evidence` object with:

- `version: 1`, `credit_count` clamped to three and `truncated` for additional
  credits. `eligibility` contains at most the first three credit check sets.
- Fixed booleans `explicit_credit`, `explicit_currency`, `unambiguous_reference`,
  `valid_receiving_account` and `trusted_reference` reproduce the five existing
  predicates. `trusted_reference` uses the existing reference-candidate method.
- `strict_profile_identity` reports presence only. It is informational and must
  never become an additional eligibility requirement.
- `tatra_profile_stage` is the first failed strict-profile stage, evaluated only
  for the existing verified sender/domain alignment, Tatra sender matcher
  (including already-supported aligned subdomains) and a bounded string body.
  Other inputs yield `not_evaluated`.

The strict parser exposes a pure stage diagnostic through its existing validation
path. `parse(string): ?array` retains identical evidence, reference casing,
identity construction, validation order and rejection behavior. Allowed stages
are `unsupported_content`, `greeting_or_line_count`, `credit_header`,
`credit_datetime`, `creditor_or_amount`, `balances`, `transaction_description`,
`trusted_reference`, `tail` and `matched`.

Diagnostic failure produces only `{version: 1, status: unavailable}` and cannot
change the original review reason, result or financial behavior. No raw text,
account, address, amount, currency value, reference, identifier, content hash,
DTO serialization or exception message may enter diagnostic metadata.

## Ownership and acceptance gates

Backend owns the confirmation-service diagnostic, strict parser projection and
two dedicated test files. Main owns documentation, generated API digests,
integration and publication. Independent security accepted the bounded design;
independent QA and security must review the frozen implementation.

Require baseline parser/evidence/identity parity, every missing eligibility bit,
strict-profile stage cases, bounded output and hostile values, diagnostic-failure
isolation, zero provider calls on review and unchanged successful processing.
Run the relevant existing bank regression suite on synthetic isolated targets,
style/static/API checks, then fresh exact-head CI and review.

This adds no raw-content retention, capture trigger, activation, queue replay or
financial retry. It cannot backfill the 20:15 audit or confirm that payment. No
new real transfer is a diagnostic prerequisite; a later normal bank notification
can supply the new safe metadata after compatible workers are deployed.
