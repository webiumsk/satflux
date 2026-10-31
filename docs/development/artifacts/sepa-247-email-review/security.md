# Independent security review: PR412 email-reference follow-up

Reviewer: `sepa247_security` (agent identifier), independent of the implementation author.
Reviewed base: Satflux `4b607bb2` plus the frozen 18-file source/test/generated-contract/localization/documentation manifest. Evidence artifacts are excluded from that manifest to avoid self-reference.

## Disposition

Accepted for the bounded source/publication follow-up; no unresolved blocking finding remains. Exact manifest SHA-256: `0693db66ed0f3f4c5cd572f65ea36633b9eb3d7f1ab60f5cbc9595f835c21a90`.

The important capability consequence is explicit: all currently recognized Tatra banka and SLSP email formats require manual verification. This is a deliberate fail-closed result, not evidence that automatic classic-format email confirmation still works. FIO/NOP provider behavior and explicit manual confirmation are unchanged. No merge, deployment, bank operation or package installation is approved by this review.

## M1: remittance labels could become invoice-allocation evidence

Security severity: Medium; major data-integrity/trust-boundary defect. Existing route-store ownership, receiving-account, amount/currency and bank-origin checks constrain the issue to allocation within the same store/account. No cross-tenant authorization bypass or fabricated bank credit was demonstrated.

Baseline `TatraBankEmailParser::parseClassic` scanned the normalized full body with `explicitE2eReferences`. A line labelled `Referencia platitela: QR-...` inside payer remittance could become the sole accepted E2E candidate. Numeric VS extraction could likewise be marked explicit even though the label boundary was not proven bank-owned. A valid opening credit header and DKIM/DMARC-attested sender do not establish which apparent labels inside remittance belong to the bank's transaction structure. Removing E2E extraction alone would leave the VS fallback.

Mitigation: current Tatra classic and compact parsers leave `e2eReference` null and `isVariableSymbolExplicit` false; the full-body E2E extractor is removed. SLSP already leaves these proof fields unset. Generic import metadata remains available, including variable symbol/reference fields and `toRow()` output. Existing service eligibility now rejects these rows before retrieving merchant credentials, fetching plugin settings or sending a report. A future parser may populate trusted proof only after a genuinely supported bank-owned field boundary is established; an arbitrary body label is insufficient.

Independent reproduction: 30 assertions passed across five synthetic baseline/current comparisons: remittance QR, remittance numeric VS, genuine VS metadata plus an injected QR line, an unproven classic label and HTML-wrapped remittance. Actual baseline parser plus actual candidate-selection helper selected the injected QR/VS. Current source selected no candidate, preserved ordinary import rows and retained the opening credited amount/currency/receiving-account evidence. These tests used a read-only source/vendor mount in a no-network PHP container, without application boot, database, API calls or money movement.

The one-off baseline/current probe and baseline class snapshot are held only in this reviewer's disposable workspace; they are historical reviewer evidence, not a claimed repository command. Durable source regressions are in `TatraBankEmailParserTest` and `SepaBmailTest`, including QR, VS, HTML and SLSP injection paths with no outbound report. Service positive-contract tests now explicitly construct trusted synthetic rows and do not claim any current production email format provides those proofs.

Disposition: source mitigation accepted.

## M2: unknown provider outcome was counted as reported

The exact provider contract returns `unknown` for an unrecognized/foreign reference. Previously the consumer counted that result and returned `unknown` without the required manual-review result. This did not demonstrate an actual paid-state transition; it misrepresented unresolved reporting in the consumer response/audit record.

Mitigation: an `unknown` outcome sets `requiresReview`, does not increment `reported`, and cannot be hidden by a later settled result. Existing recognized settled/duplicate/review handling and retry identity remain unchanged. Reviewed tests cover unknown alone, before settled, after settled and mixed with review; they assert the final response and `sepa.bmail_processed` audit metadata.

Null/unrecognized/malformed successful provider responses remain outside the exact provider's documented result contract. No additional allowlist change was required for this bounded fix: current live email parsers cannot reach automatic reporting, and no paid-state bypass from such a response was established. Off-contract response-accounting hardening remains a separate limitation, not a claimed completed change.

Disposition: source mitigation accepted.

## Nested origin schema

`bank_authentication` remains optional for review-only intake. When supplied in the documented native webhook schema, it requires `dkim_result`, `dkim_domain` and `dmarc_result`. Source and regenerated webhook contract agree. Independent JSON traversal verified the three nested required members and that the containing object does not require `bank_authentication` itself. Runtime origin verification still refuses missing/incomplete/non-bank proof; schema changes do not create a trusted transaction-reference boundary.

## Preserved security and operational boundaries

No authentication, CSRF/session, store ownership, merchant-key selection, entitlement, payment recording or bank transport behavior changed in this delta. Generic company bank importing retains its metadata/flow and is not claimed secured by this store-scoped SEPA repair. Existing FIO/NOP, package identities, Data Protection and same-reference recovery guarantees remain attributable to the separate provider qualification.

EN/SK/CS/DE/ES setup hints and public documentation now state current email manual verification explicitly. The integration record separates earlier historical automatic-classic-format assumptions from the current fail-closed capability. A trusted native MTA alone cannot re-enable automatic email matching until reference provenance is supported.

## Verification attribution

This reviewer independently executed the 30 parser/helper assertions, generated-schema checks, source/data-flow review and `git diff --check`. No stateful database or full product suite was run by this reviewer. Author/coordinator report focused 103 tests / 276 assertions and existing PHPStan/Pint/API/lint/typecheck/locale gates; those are separately attributable and must not be relabelled independent execution. Fresh exact-head CI remains a coordinator publication check.

Graphify was queried from the existing primary graph; targeted reads followed. No graph install/rebuild, dependency installation, source/index/shared-state write, production environment, merchant credential, live bank data, Git publication or deployment was used by this reviewer. Private evidence files are the reviewer's only writes.
