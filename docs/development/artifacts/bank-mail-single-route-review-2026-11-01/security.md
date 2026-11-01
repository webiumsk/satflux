> Final follow-up review evidence. Redacted private fixture/runner paths are
> not committed runnable tests; use the public commands in README.md.

# PR414 follow-up - independent security review

Reviewer: `/root/wallet_bmail_security`, independent of implementation.
Repository: Satflux / `webiumsk/satflux`.
Reviewed published parent: `12e00fa4ff97fc8be2e01de6061ef4c13dbedf07`.
Original integration base: `c90caa40e048cdf2b740e0195fd7198bd5209fe7`.
Scope: frozen follow-up 10-file manifest `FOLLOWUP_SHA256SUMS` plus exact baseline
restoration of compact parser/unit file. API/docs reviewed separately at
`FOLLOWUP_DOCS_SHA256SUMS`; main owns final count/status edits and Git publication.

**Disposition: ACCEPTED for these frozen follow-up bytes. No remaining blocking
security findings.** Original 12-file date-normalization acceptance is historical
and superseded; it must not be used to approve the withdrawn financial-policy
change. Source publication/exact-head CI and merge/deployment remain separate.

## Reassessment and resolved findings

### Major review / medium accounting availability: date-only normalization collision

At 12e00fa4, optional `!d.m.Y` assigned midnight to otherwise indistinguishable
same-day compact credits without bank transaction IDs. Existing
BankTransactionDedupe includes booked_at and existing Importer skips an equal
company-scoped hash; a separate legitimate credit could therefore disappear from
the local import. Actual bank money is not stolen by this behavior, but the
accounting/import event can be silently lost. The earlier review documented this
collision yet underweighted its material product/data-loss impact. Its prior
acceptance did not authorize that tradeoff and does not override the major finding.

The smallest safe response is implemented: restore TatraBankEmailParser and its
unit test exactly to c90caa40. Dedupe, Importer, schema, historic records and
reconciliation policy remain byte-identical. Native/MIME retry qualification now
uses a classic message with a bank-supplied booking timestamp. A separate compact
regression proves two otherwise identical notifications processed seconds apart
remain two rows under the legacy policy instead of new midnight suppression.

This preserves a known limitation: date-only/missing-date compact retries can
produce extra rows because processing time is still part of their existing key.
It is not a general idempotence repair. A genuine stable bank-event identity would
need separate bank-format, collision, retention/replay and historical recovery
design. Message-ID, MIME digest and rendering identity must not be invented as
bank-issued transaction identity. No broader design is necessary for one-route
transport compatibility. No historical rows were rewritten or automatically
reconciled. If any affected rows had been created, retain their evidence and
review manually; deployment is not inferred from source or CI.

### Minor availability/privacy: invalid optional sender vetoed otherwise valid MIME

The controller formerly validated optional envelope sender as a required-to-be-
usable value whenever present, allowing bad metadata to reject valid signed MIME.
It now retains only nonempty UTF8 strings <=255 bytes with no CR/LF/NUL and encrypts
them. Unusable optional metadata is discarded without entering the queued payload;
Company then falls back to MIME From. A usable but unsupported sender still retains
legacy priority rather than opportunistically falling back to another identity.

Store identity still comes exclusively from existing original MIME proof. Sender
is not origin attestation and cannot change recipient owner, dispatch policy,
signed From/To, signature or SEPA credit/account/reference requirements. No privacy
or trust relaxation was found. Existing raw job ciphertext, owner pinning and
sanitized retry/failure behavior remain intact.

## Exact independent verification

All 10 manifest hashes matched before and after tests. Reviewer's follow-up test
copy is a fresh git archive of c90caa40 plus only the frozen 10 files, so it cannot
accidentally retain the reverted date change. No production .env/config cache was
copied. Resolved preflight confirmed testing, SQLite :memory:, array cache/session/
mail, sync queue and synthetic BTCPay URL/key; container network was disabled.
Dependencies were existing read-only mounts. PHP 8.3.31 / PHPUnit 11.5.50; mounted
Node 24.22.1 for existing actual subprocess fixtures.

Executed:

```sh
php -d memory_limit=1G vendor/bin/phpunit --configuration phpunit.xml --do-not-cache-result \
  <private-security>/SecuritySingleMimeBoundaryTest.php \
  tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankMimeConfirmationTest.php
```

**PASS: 50 tests / 334 assertions**, exit 0, 36.366 seconds, 66.50 MB. This is
40 repository cases plus 10 private security cases; private 10/61 are included,
not an additional summed result. Output: `followup-php.log`. Tests cover dropped
invalid sender variants, usable sender precedence, actual strict signed Store
positive proof, Company import privacy, classic retry stability, compact nonloss,
owner/policy/digest/schema failures and immutable encrypted retry/failed state.

The unchanged Node runtime's previous **42 tests / zero skips** and **12 private
probes** remain attributed to the earlier exact-identical runtime bytes; they were
not rerun needlessly for this PHP-only follow-up. Strict cli/verifier/proof service
comparisons and baseline financial parser/unit/Dedupe/Importer comparisons are
empty against c90caa40. `git diff --check` passes.

Author-reported, not reviewer reruns: targeted 14 PHP / 116 assertions, full six
affected suites 155 / 623, scoped Pint 3 files, full unchanged-baseline PHPStan,
manifest and diff checks pass. Independent QA reports its separate final result
to main. No prior CI result on 12e00fa4 is claimed as a new follow-up-head result.

## Documentation and operational disposition

API sender description accurately documents ignored unusable optional metadata
without a schema maxLength rejection gate. Runbook checks the effective Laravel
`bank_inbound.node_binary`, including override and cached configuration, then
that selected executable's version; generic PATH Node can differ. Local Node24
qualification and pending Node22 exact-head CI remain distinct.

Current docs must label original date-normalization reports/counts as historical
and use this follow-up as the final release disposition. No midnight normalization,
universal compact dedup, new bank identity, history repair or settlement claim is
valid for the final one-route repair. Existing Company import authority remains
separate from strict Store bank proof; worker-first route rollout and coordinated
rollback/pending-job safeguards remain required.

Current original bank EML, actual deployment/route/provider revisions and the
historical payment's settlement remain unverified. No production access or state
mutation, real credentials/messages/funds, source/Git writes or external messages
were performed by this reviewer. Only assigned private synthetic artifacts were
written. No merge, deployment, replay or forced-paid action is approved here.
