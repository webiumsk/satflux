# Independent Tatra review2 security addendum

Reviewer: sepa247_security, independent of implementation author main. Date: 2026-10-31. Repository: webiumsk/satflux, branch fix/sepa-confirmation-isolation-247. Base 52fb0e0f9977bbca809e3ca48060a431c9c6945a plus exactly the five changed files recorded in security-reviewed-source.json. Source-only follow-up to CodeRabbit review5720326942. No provider, plugin archive or cryptographic/financial matching implementation change is included.

## Disposition

Accepted for this bounded source security gate; no open blocking security finding. The base revision's original security manifests remain historical and are not rewritten. This addendum covers the exact new guard, regression and three documentation deltas only. Generated API source-hash metadata may change separately and is not part of this five-file snapshot.

## Verified corrections and impact

**Low availability issue resolved  -  unsupported non-UTF-8 MIME retried as infrastructure failure.** BankMimeVerificationService.php now checks mb_check_encoding(rawMime, 'UTF-8') after resolving the existing store owner and decrypting/size-checking the ciphertext, before JSON encoding or starting the verifier. Previously an ISO8bit byte could fail JSON_THROW_ON_ERROR and cause pointless queue retries. The new branch uses the existing generic unverified_bank_mime review event and returns null. It performs no transcoding, sets no verified marker, makes no provider call and cannot confirm payment. It does not log or persist decoded MIME, exception content or a new raw-message field. DNS/process/transient failures retain their separate retry path.

The added BankMimeConfirmationTest sends a form HTTP request containing a synthetic invalid UTF-8 byte, checks its invalidity explicitly, prohibits the protected verify invocation, expects acknowledged intake and a generic review audit, and asserts no outbound HTTP. Using form input avoids JSON encoding the unsupported bytes in the test harness itself. This is the correct regression boundary for the original failure; crypto acceptance behavior remains unchanged.

**Documentation routing compatibility corrected.** The runbook now separates default store ps recipients onto bank-inbound.mime from default company pay recipients onto the parsed bank-inbound endpoint, with non-overlapping anchored expressions and stop(). These defaults match config/bank_inbound.php and the actual address resolver; the document explicitly requires adapting configured prefixes/domain. It warns against redirecting a shared catch-all to the store-only MIME service, which acknowledges intake before rejecting company ownership during queued processing. It also accounts for catch-all priority/duplicate-format delivery. No Mailgun route is changed by this source documentation patch, and parsed company import gains no bank-origin settlement privilege.

**Historical/current capability documentation corrected.** The earlier sepa-247-email-review README explicitly identifies cd951b3b as the temporary manual-only historical snapshot and links the existing later Tatra qualification README. The BMAIL design describes the verifier's earlier absence in the past tense and points to the current candidate. Original reports/manifests remain unchanged; no installed/deployed capability is inferred.

## Evidence, tests and boundaries

Independent exact five-file diff/source review, source/config/address-resolver comparison, target README existence check and git diff --check pass. No stateful PHP test, dependency install, bank call, production request or crypto rerun was performed by this reviewer. Main is executing the new regression and legacy-company suite in its verified disposable runner; attach those actual results separately before publication. The unchanged Node verifier retains the prior independent26/26 crypto/CLI result; this addendum does not claim a fresh run.

Authentication, Mailgun HMAC/replay, signed bank MIME identity, recipient/store/account binding, credit fingerprint, amount/currency and settlement logic are unchanged. Encryption-before-queue and generic private exception handling remain intact. No new session/CSRF, tenant authorization, key, billing, entitlement, recovery or raw-log path was added. Current real-bank EML interoperability/deployment remains the previously stated operational limitation.

No source/Git/shared-state/production writes were performed. Only these private /tmp review artifacts were written. Prior query-first Graphify evidence was reused: primary graph returned unrelated nodes and this worktree has no graph; no install or rebuild occurred.
