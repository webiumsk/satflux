# Chorala changelog recovery - 2026-11-02

## Observed incident

Read-only inspection reviewed Satflux master `9dfc4166` and owned Chorala source
`94ceaa3e`. This does not establish either application's deployed source revision.
The configured project's public feed contains 80 published entries; its newest
notes are the PR #406 wallet warning and PR #404 raffle correction. Their public
publication timestamps are October 28, after the last successful journal update.

The [last successful synchronization](https://github.com/webiumsk/satflux/actions/runs/40656818455)
prepared those two drafts. The following eight synchronization runs failed,
including [the latest inspected run](https://github.com/webiumsk/satflux/actions/runs/41358658402).
Both failures examined stopped in `Synchronize reviewed fragments`; their generic
diagnostic does not expose private response bodies or credentials.

The fixed journal branch has only the two original `created` records and no
`deploymentSha`. Their fragment hashes still match current source. Public GET
comparison establishes that only their body fields differ: CRLF line endings in
Chorala versus LF in source. Replacing CRLF pairs with LF restores exact equality;
titles, labels, linked posts and audience are unchanged. The public collection is
105,196 decoded bytes, below the synchronizer's four-million-byte response limit.
No production mutation, credential use, journal reset or workflow retry was made.

## Repair contract

Compare remote source suffixes and body fingerprints with CRLF pairs interpreted
as LF. Preserve original fragments, stored bodies, journal hashes and IDs. Bare
CR, extra whitespace, changed text, source links, labels, linked posts and audience
remain meaningful differences. Duplicate sources, missing managed entries and
uncertain results retain their existing fail-closed handling.

An already-published matching row is acknowledged in the journal without another
publication PATCH. A subsequent draft run may create missing reviewed drafts;
draft preparation alone does not publish them or request publication notices.
The original uncertainty and intent-before-mutation boundaries remain mandatory.

Source originally has five fragments: PRs #404, #406, #407, #408 and #409. Add the
missing user-facing notes for merged PR #411 (payment forms), #413 (SEPA-only
wallet alerts) and #415 (combined qualified Tatra confirmation/import repair).
The final #415 note covers its included #412/#414 bank-mail foundation. Internal
diagnostics and dependency maintenance do not need separate public entries.
Source changes are not a claim that these capabilities are deployed everywhere.

## Acceptance and next operation

Run the existing offline suites and targeted independent security/QA review.
Require published CRLF acknowledgement without another PATCH, safe pending-result
reconciliation, continued rejection of real changes, and unchanged original
fragment hashes. Tests use synthetic transports and disposable Git repositories.

After owner-approved merge, successful master CI can acknowledge the two existing
publications and prepare six missing drafts. There is no pending deployment
request in the inspected journal, so this draft run alone must not publish them.
Require separate publication approval and a confirmed exact deployed SHA. Only
fragments present in that deployed commit are eligible; newly added fragments
wait for a normal deployment that includes them. Do not redeploy solely to retry
an older changelog, reset publication state or resend existing notices.

## Verification and independent review

Author and independent reviewer `/root/changelog_crlf_qa` each pass all 71 cases
with `node --test scripts/changelog/*.test.mjs`, zero skips, on Node 22.18.0.
The sandbox blocks some synthetic Git/shell subprocesses with EPERM; the normal
approved execution outside the sandbox passes. Node syntax and whitespace checks
pass. The committed-fragment validator initially sees the five baseline notes;
direct schema/fingerprint/source-uniqueness checks accept all eight working notes.

Independent QA reproduces the incident against baseline `9dfc4166`: CRLF on two
synthetic already-published rows stops processing before a write. Fixed draft
processing acknowledges both, creates six later drafts, performs zero publication
PATCH requests and creates nothing on a repeat. Raw bodies, original hash records
and source identities remain unchanged. Eleven further adverse scenarios reject
without writes. Every transport is synthetic; no production test was run.

Independent reviewer `/root/changelog_crlf_security` accepts 92 memory-only cases
and 310 assertions, including all 64 mixed-newline combinations, bare/doubled CR,
text/source/metadata drift, duplicate binding, uncertain-result recovery and the
two historical hash goldens. No blocking finding remains. Acceptance binds these
frozen SHA256 values:

```text
2a453cc8a1abbcf5342c6b970234fb5eb4d9bc78cbb0c7a7e25f3f1010dd29a7  scripts/changelog/sync.mjs
ca0b7947b3a71227e441ff153e2bcfad32bb6bcf64c8ed552f3aae7c8ead0141  scripts/changelog/sync.test.mjs
033ab678266edb870a8d01417af3d0a4f3fd5975226e5427d9bb881bd85c5750  .changelog/entries/btcpay-247-form-compatibility.json
b5e74d5a75e42dd701007d92336333bd5e86441e8e759b6ddd3257a1d66f1ea7  .changelog/entries/sepa-wallet-alert-fix.json
5d3356c293197807729aa8664bdcc6dd03decc284dbe13b453bcee29bea3f811  .changelog/entries/tatra-payment-confirmation.json
```

The isolated worktree has no Graphify graph; the primary query supplied no
dispatch edges. Targeted reads were used without rebuilding or installing tools.
New-head CI is separate evidence. No merge, deployment, live draft creation or
publication is part of source preparation.
