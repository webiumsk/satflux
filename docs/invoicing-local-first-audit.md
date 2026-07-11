# Invoicing local-first audit

Audit of the SATFLUX Invoicing module and its Evolu-backed local-first data layer.
Every finding below was verified against the current codebase (branch
`audit/invoicing-local-first`, 2026-07-11). No real secrets, mnemonics, personal
data or production payloads appear in this document.

Evolu version in `package-lock.json`: `@evolu/common 7.4.1`, `@evolu/web 2.4.0`,
`@evolu/vue 1.4.0`. All API usage below is against these versions.

## Priority summary

| ID | Severity | Status | Title | Needs product decision |
|----|----------|--------|-------|-------------------------|
| F1 | Critical | Confirmed | Recovery mnemonic persisted as plaintext in `localStorage` | Yes |
| F2 | Critical | Confirmed | Issued documents are not immutable snapshots (supplier/buyer resolved live) | Yes |
| F3 | High | Confirmed | Offline invoice numbering falls back to local `max + 1` | Yes (legal/UX) |
| F4 | High | Confirmed | Issued document is mutated in place via generic CRDT `update` | Partial |
| F5 | High | Confirmed | Financial math uses JS floating point end to end | No (but needs migration) |
| F6 | Medium | Confirmed | Production CSP is opt-in and `connect-src` allows all `https:`/`wss:` | No |
| F7 | Medium | Confirmed | Matomo analytics script loads on invoicing pages in the same origin as the plaintext seed | Partial |
| F8 | Medium | Partially mitigated | Client recurring runner has no atomic lock across tabs | No |
| F9 | Low | Not found | WooCommerce/BTCPay events not idempotent | n/a (already idempotent) |
| F10 | Low | Not found | Server-side PDF leaves plaintext temp files behind | n/a (cleaned in `finally`) |

---

## F1 — Recovery mnemonic persisted as plaintext in `localStorage`

- **Severity:** Critical · **Status:** Confirmed · **Product decision:** Yes
- **Files:** `resources/js/services/accountSeed.ts`
  (`PERSISTENT_ACCOUNT_MNEMONIC_KEY`, `storeAccountMnemonic` L58-71,
  `hydrateAccountMnemonicSession` L74-93, `getStoredAccountMnemonic` L95-115).

**Current behavior.** On sign-in the 24-word recovery phrase is written verbatim
to `localStorage` under `satflux.account.mnemonic.persistent.v1`
(`localStorage.setItem(PERSISTENT_ACCOUNT_MNEMONIC_KEY, normalized)`, L67) in
addition to `sessionStorage`. The same phrase is the Evolu `AppOwner` seed
(`evoluOwner.ts deriveEvoluOwnerMnemonic` — "Evolu AppOwner = same 24-word
Satflux recovery phrase") and therefore the key that decrypts every invoicing
row locally and on the relay.

**Failure / attack scenario.** Any JavaScript running in the origin
(an XSS payload, a compromised third-party or CDN script, a malicious browser
extension acting on page context) can read `localStorage` synchronously and
exfiltrate the phrase, which yields full offline decryption of all invoicing
data and impersonation of the owner on the relay. `localStorage` also survives
tab close and is trivially recovered from a stolen/backed-up profile
(passive disk access).

**Impact.** Complete compromise of the E2EE model for a user whose browser runs
hostile same-origin code, and plaintext seed at rest on disk.

**Recommendation (needs product decision — see "Open decisions").** The options
differ in their threat model and UX:
- Keep `sessionStorage` only (drop the persistent `localStorage` copy): removes
  the at-rest plaintext and the cross-tab-persistence, at the cost of
  re-entering the phrase after a full browser restart.
- Wrap the persistent copy with a user passphrase via WebCrypto (PBKDF2/HKDF →
  AES-GCM) using an audited primitive already in the tree
  (`@noble/hashes`); protects against passive disk theft but **not** active XSS
  (the app can unlock the key, so hostile in-page code can too — do not present
  this as XSS protection).
- Non-extractable key material where the platform allows it.

Do not implement a silent incompatible change. This is invariant #2 and must be
a deliberate product/security call.

**Local-first / E2EE compatibility.** All options preserve local-first; option 1
slightly weakens multi-restart offline UX; option 2 does not weaken E2EE but
must be documented as passive-only protection.

**Migration.** Backward compatible — read the existing plaintext key once, then
re-store under the chosen scheme and delete the plaintext key.

**Tests:** mnemonic never present in `localStorage` after chosen scheme; restore
still works after browser restart; logout clears all copies.

---

## F2 — Issued documents are not immutable snapshots

- **Severity:** Critical · **Status:** Confirmed · **Product decision:** Yes
- **Files:** `resources/js/evolu/schema.ts` (`document` table L228-262 stores
  `contactId` and `companyId` by reference, plus computed `subtotal/taxTotal/
  total` strings but **no** supplier/buyer identity fields);
  `resources/js/evolu/documentMap.ts` (`evoluDocumentToApi` L142+ emits
  `company_contact_id: doc.contactId` only);
  `resources/js/evolu/ephemeralBridge.ts`
  (`buildLocalDocumentEphemeralSnapshot` L712-752 resolves
  `company = localDoc.companyApi(companyId)` and
  `contact = contactsForCompany(companyId).find(...)` — i.e. the **current**
  mutable rows — at PDF/e-mail/render time).

**Current behavior.** An issued invoice row persists line data and totals, but
the supplier (company legal name, address, tax IDs, bank details) and the buyer
(contact) are stored only as foreign keys. Every render, PDF, ISDOC/UBL and
e-mail rebuilds the header from the live `company` and `contact` rows.

**Failure scenario.** Editing a contact's address, a company's legal name, VAT
number, or bank account — a routine action — retroactively changes the
appearance and the exchanged/e-faktura content of every historically issued
invoice that references it. A CRDT sync of a contact edit from another device
does the same silently. This violates invariants #3 (issued document must not
silently change) and the immutability requirement.

**Impact.** Historical/accounting documents are not faithful to their issue-time
state; e-faktura and PDF output can diverge from what the customer received.

**Recommendation.** On issue, write a versioned immutable snapshot containing the
fields enumerated in the brief (number, series, dates, currency, supplier,
buyer, lines, quantities, unit prices, discounts, tax rates/amounts, rounding,
subtotals, total, payment details, `snapshotFormatVersion`). Render issued
documents from the snapshot, never from live reference tables. This is a schema
addition (new `documentSnapshot` table or a versioned JSON column with runtime
validation) — architectural, and the credit-note/correction model is a product
decision. Do not ship an unversioned ad-hoc JSON blob.

**Local-first / E2EE compatibility.** Fully compatible; the snapshot lives in
Evolu like any other row.

**Migration.** Additive. Existing issued docs have no snapshot; backfill a
snapshot from current data with an explicit `snapshotFormatVersion` and a
`backfilled: true` marker so their lower fidelity is honest (invariant: don't
migrate history to "accurate" data that never existed).

**Tests:** brief scenarios 2-5 (issue creates snapshot; contact/product/company
edits do not change issued doc).

---

## F3 — Offline invoice numbering falls back to local `max + 1`

- **Severity:** High · **Status:** Confirmed · **Product decision:** Yes (legal/UX)
- **Files:** `resources/js/evolu/documentNumber.ts`
  (`previewNextDocumentNumber` L31-61: `maxSeq + 1`);
  `resources/js/evolu/numberSeriesCrud.ts`
  (`highestIssuedDocumentCounter` L56-80, `allocateNextNumberForIssue` L328+);
  `resources/js/evolu/documentCrud.ts` (`issueLocalDocumentAsync` L375-419).

**Current behavior.** A server allocator exists (`numberSequenceBridge.ts`
`reserveNextDocumentNumberFromStore`) and is used **only** when the company has a
`linkedStoreId` and the reserve call succeeds online. When there is no linked
store, or the reserve call fails, `issueLocalDocumentAsync` falls through to
`issueLocalDocument` → `allocateNextNumberForIssue`, which derives the next
counter from the local maximum issued number (`highestIssuedDocumentCounter`).

**Failure scenario.** Device A and B are offline, both see the same last issued
number, both issue an invoice → two definitive documents share one
user-facing number; after sync there is no reconciliation and no reissue. This
violates the "no local `max + 1` for global numbering" rule and the
duplicate-numbering acceptance criterion.

**Impact.** Duplicate legal document numbers, which is an accounting/tax
integrity problem.

**Recommendation (needs product/legal decision).** Options A (server allocator
required for definitive issue), B (per-device series), C (offline provisional +
final number on sync) from the brief. The existing `linkedStoreId` server
allocator is the seed of Option A. This needs the product/legal expectation
before implementation.

**Migration / tests:** brief scenarios 7-8 (two devices issue offline / allocate
next number).

---

## F4 — Issued document mutated in place via generic CRDT update

- **Severity:** High · **Status:** Confirmed · **Product decision:** Partial
- **Files:** `resources/js/evolu/documentCrud.ts` (`issueLocalDocument` L311-363
  uses `evolu.update("document", { status: "issued", number, ... })` on the same
  row; `saveLocalDocument` L210-278 upserts the same `document` row and keys off
  `existing?.status` but does not hard-block edits once `status !== "draft"`).

**Current behavior.** Issue is a status flip on the mutable draft row. Editing
uses the same row. There is no application invariant preventing a
`saveLocalDocument`/`evolu.update` against an already-issued document, and Evolu
resolves concurrent field writes last-write-wins.

**Failure scenario.** Two devices issue the same draft (brief scenario 4/7), or
one issues while the other edits offline (scenario 15): last-write-wins silently
overwrites the issued row's fields or produces divergent numbers.

**Recommendation.** Enforce an application-level invariant: once issued, the
document row (and its snapshot from F2) is append-only; further changes go to an
explicit revision / credit-note / payment-event record. Combine with F2 snapshot
so the issued content cannot be reached by a generic update at all.

**Tests:** scenarios 6, 7, 15, 16.

---

## F5 — Financial math uses JS floating point

- **Severity:** High · **Status:** Confirmed · **Product decision:** No (needs migration)
- **Files:** `resources/js/evolu/documentCrud.ts` (`calcDocumentTotals` L77-117:
  `net = quantity * unit_price * (1 - line_discount_percent/100)`,
  `lineTax = net * rate/100`, `fmtDec = value.toFixed(digits)`); backend mirrors
  in `app/Services/Invoicing/BusinessDocumentFromQuoteService.php` L122-126,
  `BusinessDocumentCreditNoteService.php` L106-110,
  `BusinessDocumentFromProformaService.php` L121-125,
  `RecurringDocumentGeneratorService.php` L121 — all `float` arithmetic.

**Current behavior.** Amounts are stored as decimal strings in Evolu
(`OptionalString32`) but computed with JS `number`/PHP `float` and rounded with
`toFixed`. Quantities use 4 decimals, money 2.

**Failure scenario.** Non-representable products (e.g. `0.1 * 3`), discount-ratio
re-scaling (L102-109), high values near `Number.MAX_SAFE_INTEGER`, and
per-line-vs-per-document rounding can produce off-by-a-cent or
platform-divergent results, violating invariant #5 and the determinism criterion.

**Recommendation.** Move to integer minor units for fiat and integer sats for
BTC, with an explicit decimal type / normalized decimal string for rates, and a
single documented rounding rule. This is deterministic but touches the stored
representation → needs a careful, reversible migration and shared client/server
rounding. Not a "small safe change" but does not need a product decision.

**Tests:** brief scenarios 22-25.

---

## F6 — Production CSP is opt-in; `connect-src` allows all `https:`/`wss:`

- **Severity:** Medium · **Status:** Confirmed · **Product decision:** No
- **Files:** `app/Http/Middleware/SetSecurityHeaders.php` (L40-50:
  `'connect-src' => ["'self'", 'https:', 'wss:']`); `config/security.php`
  (`csp.enabled` default `false`, L26); `.env.example` (`CSP_ENABLED=false`).

**Current behavior.** CSP is disabled unless `CSP_ENABLED=true`, so a production
deployment that forgets the flag ships with **no** CSP (not fail-closed,
violating the CSP goal). When enabled, `connect-src` permits any `https:` and
`wss:` origin.

**Failure scenario.** With the broad `connect-src`, an injected script can POST
the seed or invoice data to any HTTPS/WSS endpoint. With CSP off entirely there
is no script-src restriction at all. Combined with F1 (plaintext seed in
`localStorage`) this is the exfiltration path.

**Recommendation.** (a) Make CSP fail-closed in production (enabled by default,
loud error if misconfigured). (b) Replace the blanket `connect-src` with an
explicit allowlist: `'self'`, the configured Evolu relay `wss:` origin, the
BTCPay origin, and any required API hosts — no bare `https:`/`wss:`. Test relay
sync, PDF, BTCPay, WooCommerce, and both dev and prod builds after the change
(the brief's CSP test matrix). This is a safe, high-value P0 that does not change
the data model.

**Tests:** brief scenarios 17-18.

---

## F7 — Analytics script on invoicing pages shares origin with the seed

- **Severity:** Medium · **Status:** Confirmed · **Product decision:** Partial
- **Files:** `resources/views/partials/spa-head.blade.php` (L30-32 injects Matomo
  meta when configured); `resources/js/services/analytics.ts` (loads
  `matomo.js`, `trackPageView`).

**Current behavior.** When Matomo is configured, its script is injected on the
SPA (self-hosted; `script-src` includes the Matomo origin). It runs in the same
origin as the invoicing pages and the plaintext seed (F1).

**Failure scenario.** Any same-origin script increases the surface that could
read `localStorage`; a compromised analytics host would run with page privileges.
No evidence that invoice payloads are sent to analytics today, but the seed is
readable by anything in-origin.

**Recommendation.** Keep analytics off invoicing routes, or gate it so it never
loads on `/invoicing/*`; ensure no invoice/PII/seed ever reaches
`trackEvent`. Lower priority than F1/F6 and partly mitigated by F1's remediation.

---

## F8 — Client recurring runner has no atomic cross-tab lock

- **Severity:** Medium · **Status:** Partially mitigated · **Product decision:** No
- **Files:** `resources/js/evolu/recurringDueRunner.ts`
  (`processDueLocalRecurringProfiles` L77-135 reloads a fresh snapshot per
  iteration but has no lock); `recurringCrud.ts` (`generateLocalRecurringDocument`
  advances `nextIssueDate`).

**Current behavior.** The runner is client-only (correct per the brief's privacy
model — server never generates invoices). It re-reads state each iteration, which
reduces but does not eliminate double-generation when two tabs/devices run
concurrently, because generation + `nextIssueDate` advance is not atomic and has
no idempotent recurrence key (`companyId + profileId + periodKey`).

**Recommendation.** Add an idempotent recurrence key so a given period generates
at most one document regardless of concurrency, plus handling for missed periods,
timezone/DST, template change and profile deactivation (brief §13). Keep the
client-only model; do not add server automation silently.

---

## Not confirmed / already mitigated

- **F9 — External event idempotency.** BTCPay:
  `EphemeralBtcpayCheckoutService` uses `updateOrCreate` on the unique
  `(store_id, btcpay_invoice_id)` and `markPaidFromWebhook` early-returns when
  `isPaid()` — idempotent. WooCommerce: `integration_document_inbox` has a
  unique `(store_integration_id, woocommerce_order_id)`, and the client derives a
  **stable** Evolu document id from the inbox UUID
  (`inboxDocumentStableId.ts stableDocumentIdFromInboxUuid`), so a re-imported or
  two-device import upserts the same row rather than creating a duplicate. ACK
  (`markIntegrationInboxImported`) runs after the local save in
  `importIntegrationInboxEntry`, and `markImported` deletes the inbox row
  (no long-term plaintext retention). The originally-suspected duplicate-document
  problem is not present.
- **F10 — Server PDF temp retention.** `BusinessDocumentPdfService` unlinks temp
  PDF/ISDOC/ZIP paths in `finally` blocks (L63-70, L89, L130-132). No leftover
  plaintext files on success or error.
- **No Sentry/Telescope/Bugsnag** in `composer.json`/`package.json`; error
  reporting is default Laravel logging, and no code path was found that logs the
  mnemonic or a full invoice payload. (Bridges still warrant a redaction guard —
  tracked under the P1 plan, not a confirmed leak.)

---

## Phase 4 — implementation plan

### P0 — integrity and security
- **F6 (safe, no decision):** CSP fail-closed in production + explicit
  `connect-src` allowlist. Files: `SetSecurityHeaders.php`, `config/security.php`,
  `.env.example`, deployment docs. Rollback: revert middleware; low data-loss
  risk. Requires the full CSP test matrix.
- **F1, F2, F3, F4:** blocked on product decisions (seed storage model, issued
  snapshot + credit-note model, numbering model). Present options, do not
  implement silently.
- **F5:** integer money migration — schedule as its own change with a reversible
  migration and shared rounding; no product decision but non-trivial.

### P1 — recovery and operational reliability
- Bridge redaction guard (never log full invoice JSON / seed), payload size caps
  on ephemeral endpoints, sync/backup status truthfulness, F8 recurrence key,
  attachment quota (`expenseAttachment.contentBase64` is capped at ~384 KB in
  schema — verify relay limits before raising).

### P2 — longer-term product topics
- Multi-user/device revocation, E2EE blob storage, timestamp/hash receipt,
  server-side automation decisions.

---

## Open decisions (require product input before P0 implementation)

1. **Recovery seed storage (F1):** session-only vs passphrase-wrapped persistent
   vs status quo. Trade-off: offline-restart UX vs at-rest plaintext.
2. **Issued snapshot + corrections (F2/F4):** snapshot table vs versioned JSON
   column; credit-note vs revision model for corrections.
3. **Numbering (F3):** Option A (server allocator required online) /
   B (per-device series) / C (offline provisional). Depends on legal expectation.

No production data, secrets or mnemonics are included anywhere above.
