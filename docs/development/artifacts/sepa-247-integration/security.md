# Independent security review: SEPA isolation on BTCPay 2.4.7

Reviewer: `sepa247_security`, independent of the implementation authors.
Scope: Webium provider working tree over `ed250c2` with the prepared `8afed1a` repair merged; Satflux consumer working tree over merged compatibility `4ff5c32a` with prepared `885168f2` applied. Review covers the frozen source/config/tests and assigned user documentation recorded in the companion manifest. Shared publication/state documentation is outside that source manifest.

## Disposition

Source accepted for the bounded integration/publication scope. No unresolved blocking source finding remains after the positive-credit fix below. Runtime qualification, package identity and fresh exact-head CI remain coordinator/QA gates and are not claimed as executed by this security reviewer. This review does not authorize merge, package installation, bank operations or deployment.

Manifest: 22 provider files and 26 consumer files. Exact manifest SHA-256: `9d227c2ad3a9ed433f60e344d61f6131b13954fc2dabb6855ebc2d9d095f6942`.

## Finding M1: nonpositive NOP amount could pass a large tolerance

Severity: Medium. Existing behavior discovered while reviewing the credit-only integration contract; not an unauthenticated cross-store exploit or a regression introduced by the merge.

Evidence: `NopNotificationProcessor.ToConfirmedPayment` maps an `ACCC` notification with any parsed decimal amount. The previous common `SepaMatchingService.Evaluate` only compared amount with `amountDue - amountTolerance`. For a 1 EUR invoice and an allowed 10 EUR tolerance, authenticated/integrity-consistent zero or negative NOP amounts could therefore reach the recorder and mark the plugin request confirmed. FIO and the b-mail request boundary already reject nonpositive amounts.

Mitigation reviewed: `SepaMatchingService.EvaluateBankEvidence` now rejects `confirmation.Amount <= 0m` after the bank-origin check and before account/hash validation, dedup reservation or external recording. The separate explicit manual path is unchanged. Reviewed matcher SHA-256: `ed21c325597b06127eb964a4aee68ca9580f596778ae8d0d8d8fa6b4aad52adc`.

Regression source: `SepaConfirmationIsolationTests.Nonpositive_nop_credit_cannot_settle_with_large_tolerance_and_preserves_corrected_retry` uses the actual NOP processor with raw `0` and `-1`, due 1 and tolerance 10. It requires review, no recorder call, no dedup reservation and no confirmation timestamp. A positive 0.50 correction with the same event identity must still settle under the configured tolerance. `Explicit_manual_confirmation_preserves_zero_due_behavior` checks the explicit manual boundary remains unchanged. Reviewed test SHA-256: `5ef2eb1ab6b0d2ad71f63d98f646b8c1e3cbe5f878a92f1bfbeb9c1119091c30`.

Disposition: source mitigation accepted; coordinator/independent QA must execute the PostgreSQL regressions before delivery.

## Verified trust boundaries

- Provider `SepaApiController` retains Greenfield `CanModifyStoreSettings`; cookie `UISepaController` retains the equivalent permission and automatic antiforgery validation. Report and manual controllers scope request lookups to the authenticated route store; the matcher independently uses store plus reference in a row-locking query. `SepaPaymentRecorder` independently rejects a BTCPay invoice whose StoreId differs from the stored request.
- Automatic matching requires a recognized bank source, verified origin, a positive credited amount, valid immutable request IBAN and matching observed receiving-account evidence. Current settings cannot substitute a different beneficiary/account for an existing request. Complementary genuine feeds are permitted without replacing the original backend snapshot.
- FIO uses the authenticated statement's observed receiving IBAN, rejects non-credit amounts and gives explicit primary references precedence. Multiple fallback QR references become review evidence instead of an arbitrary settlement choice.
- NOP compares the preserved raw amount/hash material against the original request account. A conflicting creditor field fails even when another part appears plausible. The checksum binds the bank-provided data; it is not represented as standalone sender authentication. Provider transport remains the origin trust boundary.
- Invalid bank evidence cannot reserve a dedup identity or call the recorder. Valid bank identities are scoped by store/source family/original account and reserved before recording under the existing unique index. Per-reference row locks and the host's retry execution strategy preserve the stable reference-based external payment identity. Recorder failure rolls the local transaction back and propagates for retry.
- NOP recovery fetches retained notifications without filtering by last polling time as transaction-ID creation time. Pagination is bounded by count, bytes, page limit and timeout; continuation must remain on the approved HTTPS provider and cash register, with constrained cursor parameters. Redirects are disabled. Incomplete/failing fetches do not advance success checkpoints; MQTT processing failures schedule retained-set recovery while connected.
- Satflux webhook authentication fails closed on absent/invalid native secret or Mailgun signature key. Only authenticated native receiving-MTA input may attest DKIM/DMARC bank identity. Mailgun delivery authentication and raw MIME authentication headers cannot set the verified-bank marker. Supported sender and signing-domain alignment are checked independently.
- Satflux bank-mail confirmation requires explicit bank-credit header evidence, explicit currency, a full checksum-valid observed recipient IBAN and unambiguous labelled transaction reference. Payer remittance text cannot supply an opening credited amount. Generic/compact/SLSP forms without complete evidence remain manual-review only.
- Satflux refuses automatic reports when settings omit a valid integer `confirmationContractVersion >= 2`. New plugins require the new proof fields even from old consumers. Old/new pairings fail closed rather than ignoring unknown fields and settling.
- Consumer SEPA routes retain Sanctum/verified-email middleware and server ownership checks, including the existing audited admin/support exception. The job resolves the inbound store owner and uses that owner's scoped merchant key. No new provisioning-key use, raw provider-ID browser exposure, entitlement change or encryption/webhook-trust bypass was introduced.
- Transport failures propagate through the existing queue retry path. The added `SepaBmailTest.failed_report_can_retry_with_the_original_payment_identity` independently checks the same complete report payload/dedup identity on a synthetic failed response followed by a replay.

## Preserved boundaries and limitations

- The existing anonymous checkout-manual endpoint remains an explicit merchant-controlled opt-in (`CheckoutConfirmEnabled`, default off). It is not bank authentication, and this integration neither broadens that endpoint nor claims every manual action requires a logged-in session. Authorized API/UI manual confirmation remains separate from automatic bank proof.
- Plugin transaction commit and BTCPay external payment persistence are not one atomic transaction. Recovery depends on same-reference idempotency and reconciliation. The patch does not promise global arbitrary cross-reference/cross-source event identity or remove upstream notification-retention limits.
- Receiving-MTA verification must actually be implemented/configured in the deployment; a synthetic attestation test does not prove deployed DKIM/DMARC. No real bank capability, certificate, credit timing, merchant configuration or money movement was tested.
- Generic company bank importing is preserved and is not represented as secured by the store-scoped SEPA bank-origin repair.
- Exact package assembly/archive identity and installed production equality remain coordinator qualification tasks. No downgrade to an old provider that accepts unbound reports should be used as a workaround.

## Reviewer execution and provenance

The reviewer performed only source/diff/negative-scenario analysis and non-mutating checks. Native `bash scripts/verify-btcpay-host.sh` passes and the restored host independently resolves to `2868deedd9d911b715fec7306ea9cada36d86bff`. Version 0.8.2.0, minimum host >=2.4.7 and EF Design 10.0.14 are preserved; the CI file provisions the explicitly named disposable SEPA database.

All 11 changed consumer production PHP files were independently compared byte-for-byte with prepared `885168f2` and remain identical. The additional consumer PHP delta is the reviewed retry regression. Both target source diffs pass `git diff --check`; no conflict markers were found in the bounded provider scope. Current source hashes are retained in the companion manifest.

Graphify's existing primary query was performed by the coordinator and returned irrelevant heuristic nodes; target worktree graphs are absent. Targeted reads were used without installing or rebuilding graph tooling. No source/index writes, dependency installation, stateful database tests, production environment access, live bank/API calls, credentials, Git publication or deployment were performed by this reviewer. Runtime counts reported by authors/main remain separately attributable.
