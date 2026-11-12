# ePostman manual outbound implementation

Date: 2026-11-12. Receiving base: `d7e2063e` (PR441, not assumed merged or deployed).
Branch: `feat/efaktura-epostman-outbound`.

## Actual sandbox follow-up - 2026-11-12

This follow-up supersedes the initial local-only evidence below. The owner
authorized actual test-network Invoice/CreditNote sending to a receiving
Recommand Peppol Test Network Playground. Production and active owner previews
remain untouched; no merge or deployment has occurred.

Preflight reproduced and corrected three existing export defects: explicit
alphanumeric participant IDs were reduced to digits, Slovak IČO used Belgian
scheme 0208 instead of 0158, and mandatory legal names disappeared when an
optional registration number was absent. Preserve literal qualified identifiers
and non-SK legacy derivation; emit PartyLegalEntity/RegistrationName for both
parties independently of CompanyID. Full official OASIS schemas pass for both
document types. This does not establish all national business rules.

The first actual run verified HTTP202 processing, identical bytes/UUID/provider
receipt after a controlled response loss, and zero-HTTP cached replays. The owner
then confirmed both documents were rejected by national validation: a Slovak
buyer's electronic address must use 0245 and its DIČ. The omitted rule code is
not inferred. Missing IČO/legal-form entries were warnings, not the reported
blocking error. The original rejected attempts and encrypted evidence remain
intact; changed documents never reuse those UUIDs.

The domestic ePostman service now requires both actual party countries to be SK
and both participants to equal 0245 plus that party's ten-digit DIČ. It repeats
the check when reloading the locked server document. Alternative schemes,
missing/mismatched DIČ and changed countries fail before provider HTTP. Generic
qualified identifiers remain valid at the builder layer; no silent rerouting or
country substitution is introduced.

The owner registered a separate synthetic DIČ recipient in the test network.
Recommand's enterprise scheme selector ends at 0240, while its country mapping
defaults Slovakia to 0245. Saving the ten-digit enterprise number with no
explicit scheme allowed the owner to register the required address. This is not
a migration of the existing real-company registration. Sources:
[country defaults](https://github.com/recommand/recommand-peppol/blob/4f15848213adad86c07ed60cad271387208d108a/utils/countries.ts),
[selector list](https://github.com/recommand/recommand-peppol/blob/4f15848213adad86c07ed60cad271387208d108a/utils/iso-icd-scheme-identifiers.ts)
and [Slovak number rules](https://github.com/recommand/recommand-peppol/blob/4f15848213adad86c07ed60cad271387208d108a/utils/identifiers/countries/slovakia.ts).

A separately reviewed harness, key, SQLite database and encrypted fixtures retain
the previous run unchanged. Offline preparation passes both full XSDs with zero
attempts/HTTP. Main then sends two new business documents: Invoice and CreditNote,
each EUR1.23. The Invoice's real HTTP202 is followed by a controlled response
loss; a new process replays the exact wire body and UUID and receives the same
provider receipt. The CreditNote also receives HTTP202. Both cached replays run
with networking disabled and make zero HTTP requests. Exactly two attempts and
two distinct provider receipts remain, with accepted request staging cleared.
The owner reports Delivered in ePostman but no documents in the same Recommand
playground after removing filters and refreshing. Screenshots confirm C3 OK and
test tax-report acceptance. These do not establish arrival in Recommand.

An unauthenticated public-routing diagnostic at 20:53 UTC finds the synthetic
0245 address registered at a Slovak test SMP. Both Invoice and CreditNote service
metadata advertise Flowis G3 Test at https://test.postarflowis.sk/as4. The same
company's displayed 9950 address has no authoritative TEST NAPTR record. An
existing test-identity collision or unpublished Recommand registration is an
inference; no provider-internal logs or cryptographic verification of the SMP
signature were obtained. Do not infer successful arrival at either portal from
this routing observation alone. A new checksum-valid test-only DIČ is confirmed
absent in authoritative TEST DNS and awaits owner registration. Verify the new
published AS4 route before any further send; retain all previous evidence.

Sources: [public service group](https://t-sml.peppol-smp.sk/iso6523-actorid-upis%3A%3A0245%3A9999900009),
[current SML zones](https://openpeppol.atlassian.net/wiki/spaces/PTPUB/pages/5059608580/SML%2BInsourcing)
and [OpenPeppol NAPTR migration specification](https://docs.peppol.eu/edelivery/changelog/2025-04/Peppol%20CNAME%20to%20NAPTR%20Migration%20Process%20v1.0.0%202025-04-17.pdf).

Independent QA passes 69 PHP tests / 324 assertions, 14 DIČ/country boundary
cases, 26 read-only prepared-state checks and 59 actual captured-live checks.
Security passes 9 independent
profile cases / 20 assertions, including the reproduced foreign-country gap.
Main final regression passes 81 PHP tests / 383 assertions, Pint 8 files and
PHPStan 4 classes. Earlier follow-up checks pass 105 PHP / 784 assertions,
66 frontend tests / 4 files, unchanged typecheck 110 inherited / no new, scoped
ESLint, API export and build 7,659 modules in 3m59s. Counts overlap. The inherited
RaffleApiTest metadata deprecation and existing build warnings remain unchanged.
No credentials, original XML, provider receipt IDs or machine-specific paths are
included in this record. Actual captures remain private and encrypted.

## Authorization and bounded milestone

The owner authorizes proceeding sequentially after the provider comparison. Start
with manual ePostman sending for issued eligible Slovak B2B invoices and credit
notes, reusing the existing server and local-first submission entry points.
Other providers, production operations, automatic sending, billing and the owner's
running receiving preview are outside this milestone. Existing receiving custody
and the independent LLC remain unchanged.

The initial proposal included delivery-state tracking. Current official evidence
establishes that SAPI credentials cannot read outbound delivery state. This
milestone therefore exposes processing acceptance, uncertain submission and a
provider portal link honestly. Automatic delivery/tax-state tracking requires a
separate Enterprise API credential or authenticated webhook integration and is
deferred; it must never be simulated by treating the submission reply as delivery.

## Verified provider contract

- Fixed production/test origins remain the existing ePostman presets. Send JSON
  to `POST /sapi/document/send` using SAPI OAuth Bearer authentication and the
  participant header. Do not follow redirects with credentials.
- The envelope has `metadata`, XML `payload`, `payloadFormat`, and optional
  `payloadEncoding`/checksum. Metadata includes document ID, full Peppol BIS
  Billing 3.0 document type ID, process ID, sender/receiver and creation time.
- A mandatory UUID `Idempotency-Key` and identical body replay for 24 hours.
  Changed body with the same key is rejected. Satflux must pin exact request
  bytes, never silently create a new attempt after an uncertain transmission.
- HTTP202 `ACCEPTED` means accepted for processing, not recipient delivery.
  Keep application status `submitted`; an explicit `REJECTED` remains rejection.
  Malformed replies and network/5xx failures may have followed successful remote
  processing and retain uncertain recoverable state.
- `GET /api/v1/invoices/{id}` is Enterprise, not SAPI. The provider explicitly
  documents that SAPI tokens are restricted to `/sapi/*`, returning 403 for
  Enterprise paths. No invented SAPI status endpoint or cross-authentication.
- Delivery and tax reporting are independent. Even a future lifecycle `ACCEPTED`
  means MLS delivery to an access point, not business approval or tax acceptance.

Primary sources checked on the date above:
[sending](https://postman.slovakodata.com/developers/odosielanie/),
[quickstart](https://postman.slovakodata.com/developers/rychly-start/),
[authentication](https://postman.slovakodata.com/developers/autentifikacia/),
[endpoint list](https://postman.slovakodata.com/developers/referencia/),
[explicit Enterprise restriction](https://postman.slovakodata.com/developers/webhooky/),
[states](https://postman.slovakodata.com/developers/stavy/) and
[public SAPI OpenAPI](https://postman.slovakodata.com/openapi/sapi.json).
Unauthenticated production/sandbox send schemas match. Whole-spec SHA256 values:
`281d68e06762768856ceb1ad2916194f9d6622ef9804df1363e52e5cc2325fd2`
and `db519060808a2fcbb47fb2ffedcb05bfd68a6c5e7e4b17d5b59bb87255cd6c4e`.
The guide's example omits the schema's timestamp field; conservative parsing must
not infer delivery from this discrepancy. No authenticated provider operation is
part of this research evidence.

## Acceptance and authority contract

1. Persist a company/user/document-bound attempt before sending, with UUID,
   connection generation, body hash, encrypted exact request and retry deadline.
   No plaintext invoice body, client secret or bearer token is stored or logged.
2. Serialize fresh owner, eligibility and connection-generation validation with
   settings changes using company/attempt row locks. A stale disabled or changed
   connection cannot submit a reserved request with old credentials. Server-mode
   sending additionally locks/reloads the persisted document and regenerates its
   content hash, fencing cancellation and content changes before transmission.
3. Identical concurrent/repeated requests cannot create a second accepted
   submission. Successful requests return their saved result. Ambiguous requests
   may retry only the pinned bytes/key within a conservative provider window;
   mutation, changed connection or expiry requires manual reconciliation.
4. For local-first documents, resolve the selected issuer's explicit bridge and
   validate identity against the authoritative company. Never choose an arbitrary
   first configured company or replace the selected issuer with another one.
   Browser snapshot credentials cannot override persisted bridge credentials.
5. Preserve existing response enums. Add `response_payload.epostman` metadata for
   accepted/uncertain/rejected state, retry permission/deadline, reconciliation
   and unavailable delivery status. No cached send `ACCEPTED` may later promote
   a submission to approved in a refresh job.
6. Disable ePostman auto-send; UI retries only when the server explicitly allows
   them. Guard initial loads and all late responses against company/document/
   connection changes. Show fixed-environment portal access for delivery checks.
7. Preserve Sanctum/CSRF, company ownership, support exception, encryption and
   existing custom-provider behavior. Receiving and custody regressions must pass.
8. Rebuilt canonical document and line amounts must exactly match issued amounts
   before reservation/provider authentication and again under the server-document
   lock. Reject lossy unit-price precision, invoice/line discounts unsupported by
   the current allowance export, and invalid/overpaid amounts. Never silently send
   a different total. Use the established canonical builder, not new tax math.

The exact encrypted request is temporary retry staging, cleared once a valid
provider document identifier confirms the submission result. Unresolved attempts
retain recovery evidence; the deadline never silently authorizes a fresh key.
Rollback refuses to drop a nonempty attempt table. No permanent outbound server
archive or new retention promise is introduced.

## UBL validity correction

Integration inspection found existing serializer output that violates the OASIS
UBL 2.1 sequence: type codes and notes followed currency/buyer reference, credit
notes included an unsupported document-level DueDate, and item descriptions
followed item names. Correct only those structural positions without changing
amounts or tax policy. Four new assertions fail against the original serializer.
The corrected synthetic Invoice and CreditNote also pass full unmodified official
XSD validation, including populated notes, due dates and line descriptions.
Partly paid documents additionally emit PrepaidAmount in the schema-defined
position, preserving the existing payable amount and Peppol BR-CO-16 balance.
Independent QA also reproduced the old discount arithmetic and fractional-price
loss. XSD alone cannot detect those errors. This milestone rejects unsupported
discounts and rounding loss rather than claiming complete allowance export.

Sources: official
[Invoice schema](https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd),
[CreditNote schema](https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd)
and [aggregate components](https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/common/UBL-CommonAggregateComponents-2.1.xsd).
Fifteen public schema files are downloaded to private temporary test storage and
mounted read-only; CI keeps offline structural regressions without requiring a
download. XSD success is not Peppol/SK business-rule validation or live delivery.

## Ownership and validation

| Task | Owner | Writable files | Evidence |
| --- | --- | --- | --- |
| Official provider contract | Researcher | None | Public schemas, sources and mismatch report |
| Durable submission and transport | Backend | Outbound app/support/models/controllers/requests, migration, config if needed, PHP tests | Synthetic HTTP, actual disposable SQL, privacy and recovery assertions |
| Manual send and selected bridge | Frontend | Relevant Vue/TS/API helpers, frontend tests, five locale catalogs | Context switching, failed loads, retry/state and bridge isolation tests |
| Integration and documentation | Main | This note, user guides, shared project state, generated API artifacts if needed | Existing checks, final manifests and source/deployment distinction |
| Independent security and QA | Separate reviewers | No product writes | Bounded diff, negative scenarios, reproducible validation and open findings |

The outbound checkout is separate from publication and the owner's active preview.
The outbound checkout has no Graphify graph. The historical primary-checkout graph/tool is available and was queried before further exploration; it does not describe this new branch. No tool installation or graph rebuild is done.
Existing matching locked dependencies are reused. PHP runs only in a verified
network-disabled disposable container with memory SQLite and isolated runtime
directories; all provider replies and identities are synthetic. PostgreSQL
concurrency evidence, if run, must be separately recorded. Run relevant frontend,
PHP, lint/type ratchets, locale/API checks and an isolated build. Live test-network
sending remains a separate evidence gate, not implied by mocked contract tests.

## Rollout and remaining gates

Apply the additive attempt migration before enabling new send controls. Preserve
attempt records across rollback so retry UUIDs and uncertain outcomes are not lost.
Disable sending if a rollback cannot honor the persisted idempotency evidence.
Do not redeploy the older receiving-only branch over new outbound state and infer
that its legacy send path preserves these guarantees.

Before production: independently accept the implementation, verify an authorized
actual sandbox Invoice/CreditNote and ambiguous retry, confirm provider production
activation/API entitlement and archive/export terms, and obtain deployment approval.
Enterprise delivery/MLS/tax-status synchronization and the next provider integration
are subsequent milestones. No provider/production operation or deployment follows
from completing this local implementation.

## Implementation and verification record

Implementation is accepted locally by independent QA and security. Their 27-path
source/test/locale manifests have SHA256
`98d67eb5beb91c7b649f56cf557b5fb35c3607fd50b97e96b7aab9880a6faf42`
(QA) and `eda03ee80802f2c68584eeb497c331ec3618a1270aead1809ac17ba7ce29905c`
(security). Main verifies source bytes before committing. Evidence is retained
privately under `reviews/epostman-outbound`; no account input is copied there.

Independent security reproduced and closed HIGH server-document cancellation/
content and MEDIUM fresh-owner fence findings. Its actual PostgreSQL probe passes
11 cases: independent committed-reservation observation before POST, same-document
send/settings/document locks, disable/cancellation/number/line/owner changes,
transaction-failure replay, stale-result projection, and JSON scheduler filtering.
Provider HTTP is simulated. The disposable PostgreSQL container was removed.
Security additionally passes final 8 amount tests / 38 assertions, 2 compatibility
tests / 9 assertions and 53 frontend tests in 2 files. No blocker remains.

Independent QA passes 104 PHP tests / 800 assertions, a final 43 tests / 215
assertions after the two compatibility corrections, 129 frontend tests in 7 suites,
3 actual-helper/component malformed-response cases and 8 financial cases. Its
four findings are closed: malformed company-bound replies, source/export monetary
divergence, unsupported discount arithmetic and missing prepaid amounts. These
counts overlap and must not be summed.

Main full frontend passes 2,953 tests in 235 files (940.85 seconds). Final typecheck
has 110 inherited errors and no new errors; the baseline is unchanged. Full lint
passes with 46 / 52 warnings; scoped strict-response lint also passes. The
production build passes with 7,659 modules in 4m35s and existing dependency-comment,
mixed-import and chunk-size warnings. API export --check, all 3 OpenAPI validators,
widget provenance, locale parity and whitespace checks pass.

Author final outbound validation passes 31 tests / 180 assertions. Scoped Pint
passes 14 backend files and the 2 main-owned UBL files; PHPStan passes 11 backend
classes and the exporter, without baseline changes. Main UBL checks pass 15 tests
/ 67 assertions, including six new regressions and nine existing exporter tests.
Both document types, including partly paid variants, invoke the full official XSD
in the isolated environment. Four structural regressions fail on the original
serializer. The sole PHPUnit metadata deprecation is inherited from unrelated
RaffleApiTest discovery.

The custom-provider compatibility corrections skip ePostman issuer selection when
a snapshot explicitly selects another provider and exclude ePostman processing
receipts before the scheduler's 100-row limit. Generic status synchronization
therefore cannot be starved by permanently submitted SAPI receipts.

No authenticated provider request, live sending evidence, production activation,
merge or deployment is part of this milestone. Actual sandbox sending remains the
next gate. A reviewed changelog fragment can be attached to the eventual outbound
PR once its canonical PR URL exists; PR441's receiving entry stays historical.
