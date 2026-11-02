# SLSP bank-mail foundation

Status: dormant preparation, not automatic SLSP payment support.
Owner authorization: 2026-11-02, prepare the foundation while a bank account
holder is sought. Base: merged PR #415, `9dfc4166`.

## Acceptance and integration contract

- Preserve the complete Tatra parsing output, CCINT/CSO/Platba fingerprints and
  all existing cryptographic, financial, ownership and duplicate checks.
- Keep Company generic imports and parser order unchanged. The existing
  `SlspBankEmailParser` is import scaffolding, not a qualified bank template.
- Select strict evidence from sender identity using the existing domain matcher,
  independently of which generic importer matched a subject/body hint.
- Route the existing SLSP sender boundary to a separate final
  `SlspBankSepaEvidenceParser`. It always returns null: no trusted E2E reference,
  explicit payment VS or bank-credit identity can originate from this profile.
- Do not add a configuration switch, guessed template, sender allowlist, DNS
  permission, MIME decoder, capture expansion, API, migration or provider change.
  Sender selection is not origin authentication. Original-MIME Store verification
  remains Tatra-only; authenticated native SLSP generic credits still require
  review before any provider settings/report request.

The shared generic Tatra importer may match a subject such as `Obrat` before the
SLSP importer. A private strict-evidence helper handles that existing fallback
without a registry reorder. A Tatra-shaped message from SLSP never qualifies as
Tatra evidence. Synthetic Tatra-shaped negative cases are not SLSP samples.

## Collecting qualification evidence with an account holder

1. Establish the exact account/product: personal George, a business channel or
   another service. Templates, email availability and timing may differ.
2. Check whether the bank offers individual notifications for credited incoming
   transfers. [George help, updated 2026-03-16](https://www.slsp.sk/sk/george-help/george/communicate-with-george/spojte-sa-so-svojou-bankou/ako-si-v-georgeovi-nastavim-notifikacie)
   documents one email per transaction for the unencrypted option, configured
   through web George. It does not establish this profile's signed fields,
   incoming-credit details or email latency. Its immediate-delivery claim is for
   push, which cannot substitute for the email channel.
3. Preserve an existing or later ordinary bank-direct incoming-credit original
   as `.eml` in the account holder's private mailbox/operator-controlled location.
   A new payment, account opening or connection to Satflux is not required for
   collecting that existing evidence. Do not forward the shared Mailgun route
   to a personal inbox or change another merchant's recipient/routing.
4. Keep the original bytes and headers private. Do not paste the message, accounts,
   reference, names, balances or signature/storage credentials into chat, issues
   or commits. Screenshots and ordinary forwarded text cannot establish the
   original signature; redacting an original changes the bytes it authenticates.
5. Report only structural facts and fixed diagnostic results. Use fully synthetic
   values and generated signing keys in later committed fixtures. A synthetic
   signature regression and a real original-bank signature are separate evidence.

## Required before implementing or enabling a real SLSP profile

Privately establish all of the following from the actual original:

- Bank signer/sender alignment, full-body signature, required signed headers,
  recipient binding, actual charset/MIME topology and unambiguous decoding.
  A visible From address, Mailgun delivery signature or PGP encryption alone
  does not establish authenticated bank financial evidence.
- An exact bank-authored positive-credit status, full receiving account,
  amount/currency and trusted QR/E2E/VS field separated from payer-controlled notes.
  Do not substitute a configured IBAN or infer settlement from a note.
- A stable credit identity for duplicate/retry handling, independent of which
  invoice reference is matched. Preserve existing historical identities.
- Account/product eligibility and actual notification behavior. No email-delay
  SLA is inferred from the public help page.

Then implement the observed strict grammar and separately qualify the original
MIME proof path. Reuse the current Store/account/reference checks and provider
confirmation contract 2 if compatible. Any missing/ambiguous evidence remains
review-only. The dormant class cannot be enabled by configuration.

## Validation scope

Required regressions: unchanged Tatra output/fingerprint goldens; SLSP root and
subdomain routing; adversarial sender display names, subject/body hints, QR/VS
injection and unsupported layouts; both generic importer choices; unchanged
Company rows and tenant binding; no provider calls for native-attested SLSP;
unchanged Store original-MIME rejection. Run on disposable SQLite/in-memory
targets with synthetic credentials and stubbed HTTP. Independent QA/security
must review the frozen source before publication. No real bank transaction or
historical mail replay is part of these tests.
