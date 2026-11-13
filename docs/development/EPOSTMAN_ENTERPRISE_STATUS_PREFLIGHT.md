# ePostman Enterprise status preflight

Date: 2026-11-12. Application baseline: a3132533.

## Authorized scope and acceptance

After confirming test-network Invoice/CreditNote arrival, the owner creates a
90-day Enterprise API key in the test account. Prepare a hidden local capture,
then verify read access to the two existing RCMD receipts. This is a prerequisite
to automatic status tracking, not an implementation of that feature. Production,
new documents, inbound acknowledgement, webhook registration and application
database/settings changes are outside this preflight.

1. Capture one key in a real terminal without echo, arguments, environment input
   or any network request. Preserve existing SAPI credentials and all old fixtures.
2. Use a separate owner-only directory/file, descriptor-based no-symlink traversal,
   exclusive creation and durable file/directory sync; failures must permit retry
   without overwriting another file or printing input.
3. The later probe may make only two GET requests at the fixed test origin to
   `/api/v1/invoices/{id}`. Obtain the IDs from the encrypted accepted RCMD results,
   not arbitrary command arguments. Disable redirects and automatic retries.
4. Bind replies to the expected receipt and document metadata. Read the direct
   Invoice object, validate its shape and keep delivery, C3 and C5 separate. Print
   only finite status/MLS fields and safe diagnostics; retain any needed raw
   evidence encrypted in separate private state. Never print keys, IDs, party
   names, invoice bodies or provider error messages.
5. Independent security/QA review precedes owner input and authenticated probing.
   Tests use synthetic data; only the coordinator performs actual provider reads.

## Verified provider contract

Enterprise requests use `X-Api-Key`. Existing SAPI OAuth tokens cannot access
`/api/v1/*`. The status detail responds with a direct Invoice object, including
`id`, `status`, nullable `mlsC3Status` and `mlsC5Status`, and document metadata.
Some metadata fields are nullable; invalid or incomplete binding must be reported
as such, not mislabelled as failed authentication or accepted as delivery proof.

The public schema is read without credentials from the documentation server.
The unauthenticated sandbox schema URL returned HTTP403; this is not a test of
the owner's new key. Actual authenticated requests remain sandbox-only.

Lifecycle `DELIVERED` denotes positive AS4 receipt; `ACCEPTED` denotes positive
MLS. `UNCONFIRMED` denotes missing MLS after the provider's timeout. These are
distinct from SAPI submission HTTP202 ACCEPTED, and from tax-report acceptance.
No state transition is inferred from an event name or from a successful key test.

Sources: [Enterprise OpenAPI](https://postman.slovakodata.com/openapi/enterprise.json),
[endpoint reference](https://postman.slovakodata.com/developers/referencia/),
[webhook authentication and contract](https://postman.slovakodata.com/developers/webhooky/)
and [lifecycle semantics](https://postman.slovakodata.com/developers/stavy/).

## Capture and review evidence

The separate `prepare_enterprise_sandbox.py` helper writes only
`storage/app/private/epostman-enterprise-sandbox/.env.credentials.json`. It has
no network client. Both author/main and independent QA pass its 16 synthetic
tests. Independent security accepts the frozen helper after 7 additional checks,
including actual PTY success/no echo, piped-input rejection and wrong-owner denial.
The original receiving capture and app runtime remain unchanged.

## Actual read-only result

The owner saves the key using the reviewed hidden prompt. Main's isolated offline
check validates its private file and the two retained RCMD bindings with zero
HTTP. After independent probe approval, main executes exactly two authenticated
TEST GETs. Both HTTP200 direct Invoice responses pass the schema and exact receipt,
client-document, participant, document-type and process binding checks.

Both documents report status `UNCONFIRMED` and C5 `OK`. The finite-output probe
initially prints C3 `OTHER`; independent offline exact-token classification of
the encrypted responses confirms `TIMEOUT` for both. No arbitrary raw field or
document content is printed. Preserve the executed probe and account for this
observed token in the future application normalizer. `UNCONFIRMED` is consistent with
the missing final MLS receipt; the owner already confirmed actual document
arrival in Recommand. Provider-reported C5 is not independently validated
statutory or production acceptance.

Probe self-tests pass 136 checks with no network or owner-input mounts; independent
QA also passes 4 full-schema cases, and security passes 10 adversarial cases.
Capture QA's 16 cases and security's 7 checks remain separate evidence. The final
offline audit also verifies all 15 original encrypted captures and 33 reviewed
runtime files are hash-unchanged. Reports
and encrypted responses stay private. No new documents, inbound ACK, webhook
registration, app database/settings change, production request, push, merge or
deployment occurs. Automatic state synchronization remains a subsequent bounded
implementation milestone, not something enabled by this diagnostic.
