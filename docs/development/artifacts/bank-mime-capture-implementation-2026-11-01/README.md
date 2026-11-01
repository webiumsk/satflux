# Private bank MIME capture evidence - 2026-11-01

Historical capture-only evidence for406e1fef. The subsequent
[ASCII wire-body repair](../../TATRA_ASCII_WIRE_PROFILE.md) changes the verifier
and safe outer capture diagnostics under its own review. Those changed files
are not expected to match this older frozen source manifest on later revisions.

Base: `037a6fa7`, branch `feat/bank-mime-private-capture`. The owner approved
implementation of the independently reviewed proposal ba1177a5, default-off.
No production activation, deployment or real-message capture occurred.

The feature retains one eligible rejected original for one operator-selected
Store. Redis7 server time fixes a maximum two-hour absolute deadline; an atomic
transition can only replace the existing authenticated-encrypted ARMED slot.
Missing/evicted state cannot re-arm it. Exact owner/generation/deadline/input
binding, 256KiB raw and 1MiB encrypted bounds, fixed diagnostics and no financial
replay preserve the approved contract. Live TTL does not erase backup residuals.

| Gate | Result and evidence |
| --- | --- |
| PHP author | [Backend](backend.md): 128 tests / 917 assertions, scoped Pint9 and full unchanged-baseline PHPStan pass |
| Node author | [Node](node.md): 56 tests pass, existing proof predicate and strict CLI result shape unchanged |
| Independent QA | [QA](qa.md), `/root/bank_mime_diagnostic_qa`: accepts frozen scope; 243 repository PHP tests / 1267 assertions, 15 private cases / 285 assertions and56Node pass, zero skips; API export check passes |
| Independent security | [Security](security.md), `/root/bank_mime_diagnostic_security`: accepts frozen15-file scope; 54 PHP tests / 475 assertions, 36 independent Node assertions and baseline-boundary checks pass, no unresolved findings |
| Main | API export/check, exporter Pint, offline OpenAPI validators, CI YAML/fixture wiring, unchanged changelog fragments and source/preserved hashes pass |

All tests use synthetic data, SQLite memory, existing locked dependencies and a
disposable Redis7 with no external network or persistence. Reviewers use read-only
source mounts and separate Redis prefixes. No production database, wallet, bank
message, merchant key, transfer or external payment API was used. Author/test
harness corrections and the temporary local dependency-path disruption are
disclosed in their reports; final combined runs pass on unchanged frozen bytes.
The local changelog check initially hit a sandbox subprocess restriction; the
same read-only check passes through normal escalation. No source workaround or
publication was used to bypass it.
Machine-specific checkout prefixes in copied reports are normalized to
`<repository-root>`; original private reports remain available for provenance.

The [15-file source manifest](source.sha256) covers reviewed runtime, tests,
configuration, environment example, CI fixture and API exporter. The generated
route-coverage artifact was refreshed and checked afterwards. The [16-file
preserved manifest](preserved.sha256) matches the base for webhook authentication,
encrypted job, owner resolution, imports, parser/evidence/dedup, provider reporting,
strict/company entrypoints, dependency locks and routes. The Node verifier changes
only by factoring its unchanged evaluation through a private safe observer;
independent projection/equivalence tests cover that distinction.

Local runtime checks use the existing Node24 binary. Fresh exact-head Node22 CI,
whole-application checks and actual deployed Redis7/worker/key/backup readiness
remain separate rollout gates. No earlier CI pass is claimed for the new head.
Current bank signing/layout and the pending payment remain unqualified without
an original message. Diagnostic output never authorizes confirmation.

See [implementation and operator instructions](../../BANK_MIME_CAPTURE_IMPLEMENTATION.md).
