# Exact Tatra Platba compatibility - review evidence

Base a29b727a. The sole production change adds exact `Platba` to the existing
anchored CCINT/CSO description alternatives. Numeric bounds, full frame, signed
reference provenance, bank proof, amount/account/currency and provider checks are
unchanged. Actual private message values are not stored here; fixtures use only
synthetic accounts, dates, amounts, references, counterparties and notices.

See the [profile contract](../../TATRA_PLATBA_PROFILE.md). The
[four-file manifest](source.sha256) freezes the parser and targeted tests;
[20 preserved boundaries](preserved.sha256) match a29b727a, including financial
services, DTO, ingress, cryptographic runtime, capture, configuration and locks.
The production parser is otherwise byte-identical to the base. Old CCINT/CSO
arrays and fingerprint goldens remain unchanged; Platba retains its own label.

## Attributed gates

- [Backend author](backend.md): 159 targeted PHP tests/719 assertions pass, zero
  skips; scoped Pint passes. Normal native, fully signed MIME and authenticated
  ASCII MIME paths use the same synthetic Platba key and bound report fields.
- Main: full unchanged-baseline PHPStan and API export/check pass. Generated API
  change is only the parser source digest. Frozen/preserved hashes and diff checks
  pass; no UI, provider source, migration, package or dependency change.
- [Independent security](security.md): 81 targeted PHP tests/346 assertions and
  839 private checks pass, including 672 old-profile comparisons. Source scope
  accepted without a verified finding or unresolved blocker.
- [Independent QA](qa.md): 338 bank regression tests/1904 assertions, three private
  ingress tests/17 assertions and 39 pure profile checks pass, zero skips.
  Qualified-provider verification runs three existing shipped tests plus four
  private changed-key cases/22 assertions against the actual unchanged SEPA DLL.
- Fresh exact-head CI and review status remain a separate publication/rollout gate;
  a previous head's successful workflow is not current evidence.

## Provider replay evidence and limits

The read-only provider is e8d7fbc, SEPA 0.8.2.0 with unchanged c1912bf qualified
DLL, host 2868deedd9d911b715fec7306ea9cada36d86bff (BTCPay 2.4.7). References are
globally keyed, generated/bound to the original invoice and Store. The matcher
locks that reference and rejects confirmed state before processing an event key;
recording uses the same reference as payment ID. A label-only replay with the
same authenticated reference cannot settle a second invoice.

Private tests exercise sequential and concurrent distinct opaque keys for the
same reference, recording retry and untrusted input. They execute the real
qualified matcher with a disposable PostgreSQL target and recording spy/model;
they do not record real host payments or establish actual bank settlement.
Different genuinely authenticated bank-reference renditions of one economic
credit remain an informational assumption, not a claimed cross-label alias
guarantee. The contemplated contract-v3/alias-table expansion is not needed for
the demonstrated issue and is not implemented.

All targets are isolated and synthetic. PHP uses SQLite memory, array stores,
sync queue, null logs, existing locked dependencies and disposable loopback Redis.
Provider tests use a fresh PostgreSQL 17 network namespace, temporary data,
explicit fixture identity, no host port and no production configuration. Reviewers
mount source read-only; new outputs live only under private temporary paths.
The coordinator stops the dedicated databases after test windows close.

No production mail, wallet, credential, database, funds, replay, deployment or
historical payment mutation is used. Source acceptance is not confirmation of
an invoice. Later normal-message runtime evidence remains necessary after
compatible web and queue-worker rollout.
