# Incomplete credit evidence diagnostics - review evidence

Base: 12f83c11. Scope: the existing incomplete-credit review audit and a pure
first-failing-stage projection through the same strict Tatra parser validation.
No actual bank original or transaction values were available to agents. The
owner's new audit narrows processing to origin-passed, parsed-credit, ineligible
evidence; it does not establish the current template or exact missing field.

The [contract](../../SEPA_CREDIT_EVIDENCE_DIAGNOSTICS.md) defines fixed capped
booleans and stage enums. Profile identity stays informational, trusted reference
uses the existing candidate rules, and every financial predicate/report payload
and parser evidence/identity remain unchanged. No capture, raw retention, replay,
new activation, migration, payment retry or provider operation is introduced.

## Evidence and review gates

- [Backend author](backend.md): 146 PHP tests/526 assertions pass, zero skips;
  scoped Pint and narrowed PHPStan pass. Structural comparison preserves the
  original validation/identity code, tail helpers, eligibility filter and complete
  accepted financial path from 12f83c11.
- Main: full unchanged-baseline PHPStan and API export/check pass. The generated
  contract change contains only the two updated source digests; endpoint schemas
  and HTTP behavior are unchanged. Diff checks and frozen-source hashes pass.
- [Independent QA](qa.md): 315 repository PHP tests/1774 assertions and 24
  private tests/233 assertions pass, zero skips. A 116-input baseline corpus
  passes 359 deep evidence/stage/identity checks against 12f83c11. The actual
  documented operator query passes one additional isolation test/15 assertions.
- [Independent security](security.md): 59 tests/224 assertions and 450 baseline
  checks across 90 inputs pass, zero skips. Frozen source and the corrected
  selected-Store operator projection are accepted without unresolved findings.
- Fresh exact-head CI/CodeRabbit remains a separate publication gate; earlier
  12f83c11 workflow results are historical for this follow-up.

The [four-file source manifest](source.sha256) identifies runtime and dedicated
tests. [Nineteen preserved files](preserved.sha256) match 12f83c11, covering MIME
proof/runtime/locks, capture, ingress, queue, DTO, provider and dedup boundaries.
The changed strict-parser stage and confirmation-service audit are reviewed by
baseline structural and independent execution comparisons rather than listed
as preserved files.

All execution uses synthetic SQLite memory, array cache/session/mail, sync queue,
null logs and synthetic provider credentials. Existing locked dependencies are
read-only. Dedicated Redis 7 is disposable, loopback-only, without external
network or persistence; reviewers use read-only source and separate cache prefixes.
No production wallet, database, token, real mail or funds are used.

Operator projection is bounded, catches bootstrap failures without exception text,
and filters the selected local Store and incomplete-credit reason server-side.
Store identifiers remain on the server; only timestamp/reason/fixed diagnostic
metadata may be returned. This avoids attributing another merchant's recent audit
to the incident. Existing audit entries cannot be backfilled. No later normal
message or actual-bank compatibility/settlement is claimed by these tests.
