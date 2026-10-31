# Tatra B-mail automatic confirmation

Status: implemented candidate under independent review on 2026-10-31. Consumer base cd951b3b, provider c1912bf. Mailgun is the actual
receiving service. No current original bank message is available locally.

## Acceptance and correction

B-mail must automatically confirm a supported, authenticated bank credit against
the intended invoice. Permanent manual-only behavior does not satisfy that goal.
The previous review proved that body labels influence reference selection; it did
not establish theft or underpayment merely because a payer chose another invoice
reference. Payer-selected QR/E2E is normal payment allocation. Bank credit/original
account/amount/currency evidence, unambiguous allocation and once-only consumption
remain required. Unknown formats or evidence failures are the manual exception.

## Evidence and bounded format

Two primary integrator examples independently show a historical Tatra classic
prefix: opening credit, three numeric balances, constrained bank/account
transaction description, then reference before recipient-note/name text.

- [Tatra B-mail](https://www.tatrabanka.sk/sk/personal/ucet-platby/internet-banking/b-mail/)
  confirms notification delivery and transaction-code/account notation; it does
  not specify a complete current email grammar.
- [ISPadmin import documentation](https://wiki4.ispadmin.eu/cz/faq/centralni-sklad/360-cz/fakturace/menu-fakturace.html)
  documents the classic ordered layout in a historical example.
- [REMP raw-email fixture](https://github.com/remp2020/crm-payments-module/blob/master/docs/tatrabanka.eml)
  independently witnesses the prefix in 2019. Its anonymized signatures do not
  establish live cryptographic verification.

Independent security accepts a narrowly defined anchored numeric prefix as
structurally defensible. No arbitrary text or skipped lines may precede the
reference. Unknown/reordered/free-text prefixes cannot provide automatic proof.
QR in the bounded reference slot is required for normal SK checkout; public VS
examples alone do not demonstrate actual current QR rendering. HTML flattening
must not create a synthetic prefix. Generic company import remains separate.

## Mailgun verification

The application currently treats Mailgun delivery authentication separately from
bank origin and implements no independent raw-message verifier. Native receipt
can consume operator attestation, but that is not an implemented Mailgun solution.

[Mailgun's May 2026 release](https://www.mailgun.com/releases/authentication-results-in-inbound-route-url-posts/)
announces inbound authentication results. Its release note does not define a
trustworthy standalone bank-domain/body-bound object. Historical X-Mailgun result
headers do not by themselves establish signer-domain identity or safe removal of
sender-provided lookalikes. Do not invent such a contract.

The concrete alternative is Mailgun raw-MIME delivery, followed by independent
aligned DKIM verification and MIME selection from the same authenticated bytes.
Use a maintained verification library; no custom signature implementation. Node22
already exists in the PHP image, so a bounded local process can avoid introducing
a network service. Enforce size/time/DNS limits, full-body coverage, signed From,
recipient/content binding and fail-closed errors. Preserve generic parsed intake.

## Event identity and evidence

Current consumer dedup hashes rendering and invoice reference and is stable only
for identical queue retries. A canonical credit fingerprint must be independent
of invoice selection and mutable free text. Do not mistake a counterparty account
or email Message-ID for an immutable bank transaction ID. If only historical
credit/date/balance/account data exist, document conservative collision behavior
and prevent multiple allocation of indistinguishable events.

Completion requires a real production-parser positive fixture through authenticated
Mailgun MIME intake, independent verification and store/account-bound reporting;
actual crypto tests with synthetic keys/DNS; injected/duplicate/reordered fields;
tampered/unsigned/partial-signature mail; replay across invoice references;
transient verification/report recovery; unchanged generic importer; independent
security and QA. Current-bank interoperability remains unverified until a current
message can be examined privately. No Mailgun route change, bank transaction,
installation or deployment is authorized or performed by source preparation.

## Implemented candidate and qualification

The original-MIME endpoint, encrypted job storage, bounded local CLI, strict
transaction parser and canonical credit fingerprint are implemented. The native
attestation contract and legacy company-import path remain compatible. Tests
exercise production PHP-to-Node verification with ephemeral synthetic signatures
and offline DNS, rather than directly injecting trusted transaction objects.
Runtime dependencies are isolated exact pins; the initial joi advisory is
mitigated by a scoped patched override and the nested audit is clean.

The [current evidence](artifacts/tatra-bmail-automation-2026-10-31/README.md) records
actual test counts, source hashes, independent reviews and remaining operational
qualification. Current Tatra delivery/signing interoperability still requires a
private original message. That absence limits coverage claims, not the safety of
matching the supported strict historical subset. No real-bank or deployment test
is inferred from synthetic cryptographic success.
