# Slovak eFaktura - implementation reference and current limitations

## Manual ePostman sending milestone - 2026-11-12

The separate outbound branch extends the receiving PR441 with manual submission
of issued eligible domestic B2B invoices and credit notes. It adds durable,
encrypted retry staging and a pinned request UUID/body, preserving uncertain
outcomes across process failure. Automatic ePostman sending remains disabled.
Submission HTTP202 means accepted for processing, not delivered or tax-approved.
SAPI tokens cannot call the Enterprise delivery-status API; check delivery and
reporting in the matching ePostman portal. Automatic status synchronization needs
a separate Enterprise credential or authenticated webhook integration.

Receiving has actual test-network evidence. Manual sending has independent review,
actual sandbox processing and identical-request retry evidence; confirmation of
delivery to the new test receiver is pending. An earlier attempt was rejected by
national validation, so HTTP202 must not be reported as delivery. Domestic ePostman
sending requires both SK parties' actual DIČ under scheme 0245.
No merge or production deployment is implied. See
[the outbound contract and verification record](development/EPOSTMAN_OUTBOUND_IMPLEMENTATION.md).


> Current ePostman receiving implementation and activation gates: [runbook](development/EPOSTMAN_INBOUND_IMPLEMENTATION.md). The overview below also describes legacy generic SAPI functionality; it is not evidence of a verified provider integration. ePostman outgoing is unavailable in this receiving scope.


> **Product status - 2026-11-12:** ePostman receiving of an Invoice and CreditNote has been verified in the actual Peppol Test Network, including encrypted original custody and local import. Guided per-company setup is documented in the [customer onboarding runbook](development/EPOSTMAN_CUSTOMER_ONBOARDING.md). A complete production Slovak eFaktura integration has not been verified. Satflux is currently not presented as a ready solution for mandatory Slovak e-invoicing. UBL export alone does not deliver an invoice, and a successful OAuth connection test does not prove document acceptance, delivery, receiving or reporting. Provider compatibility, API access and any separate fees must be verified for the selected service. Receiving-first ePostman implementation is authorized; production activation remains unverified pending Product Owner direction, provider terms and complete integration verification; no completion date is promised. Existing code, configuration, credentials, document history and safeguards are retained. The implementation and runbook below are technical reference, not evidence of production readiness or authorization to activate a provider.

Od **1. januára 2027** platí pre tuzemské B2B transakcie na Slovensku povinná štruktúrovaná elektronická fakturácia (zákon č. 385/2025 Z. z.). Dokumenty idú cez sieť **Peppol** a certifikovaného **digitálneho poštára** (CPDS). Finančná správa SR spravuje výber poskytovateľa a národné pravidlá (SK CIUS).

Satflux **nie je** digitálny poštár. Modul Business Invoicing generuje Peppol BIS Billing 3.0 UBL a voliteľne odosiela dokumenty cez **SAPI-SK** API, ak si merchant nastaví vlastné credentials u svojho CPDS.

Modul e-faktúry je dostupný len slovenským firmám (`jurisdiction = eu_sk`). **Odosielanie** (send, auto-send, Peppol sender ID) je vyhradené firmám so stavom DPH **Platiteľ DPH** (`vat_status = payer`); neplatitelia a čiastoční platitelia (§7 / §7a) vidia záložku aj sprievodcu v režime **iba príjem** - bez auto-send a bez Peppol sender nastavení, outbound API volania pre nich vracajú 422.

Rozsah povinností zákona sa pritom líši: **vystavovať** e-faktúry musia od 1.1.2027 len platitelia DPH, ale **prijímať** ich musia všetky zdaniteľné osoby, ktorým platiteľ fakturuje. Satflux to zrkadlí rozdelením eligibility (`CompanyEfakturaEligibility::supportsOutbound` vs `supportsInbound`): odosielanie a auto-send sú len pre platiteľov, **prijímanie (inbound) je otvorené každej slovenskej firme** bez ohľadu na stav DPH. Neplatiteľ a čiastočný platiteľ (§7 / §7a) vidí záložku E-faktúra v režime „iba príjem" - bez auto-send a bez Peppol sender nastavení; server odmietne `efaktura_auto_send=true` pre takúto firmu (422).

## Kto čo rieši

| Úloha | Zodpovednosť |
|-------|----------------|
| Výber CPDS na portáli FS SR (eFaktúra) | Merchant |
| Generovanie UBL / ISDOC | Satflux |
| Peppol doručenie a reporting na FS SR | Certifikovaný digitálny poštár |
| Automatické odoslanie po vystavení faktúry | Satflux (voliteľné, per firma) |
| Pre predplatné Webium LLC (WY) | Mimo SK tuzemskej e-faktúry |

## Čo Satflux už podporuje

- **UBL** export (Peppol BIS 3.0) - `GET .../documents/{id}/ubl`
- **ISDOC** export a embed do EU PDF
- **SK CIUS** polia v UBL: Peppol scheme `0158` (IČO), `0245` (DIČ), `PartyLegalEntity`, `PaymentMeans`/IBAN, UN/ECE unit codes
- Per-company nastavenia v `app_settings` (credentials merchanta)
- Async odoslanie cez `SubmitBusinessDocumentCompliance` job (za `EFAKTURA_ENABLED=true`)
- SAPI-SK JSON `document/send` (metadata + UBL payload) podľa špecifikácie CPDS
- Inbound polling `efaktura:poll-inbound` - import UBL do **nákladov** + acknowledge
- API: `POST .../efaktura/send`, `POST .../efaktura/poll-inbound`, `GET .../efaktura/compliance`, `POST .../efaktura/compliance/refresh`, `POST .../efaktura/test-connection`, `POST .../efaktura/compliance-bulk` (+ ephemeral varianty pre local-first)
- Voliteľný scheduler `efaktura:sync-compliance-status` (globálny `EFAKTURA_SAPI_SEND_DETAIL_PATH` alebo per-preset detail path)
- **Derivované Peppol ID**: sender ID sa automaticky odvodí z DIČ (`0245`) alebo IČO (`0158`) firmy; explicitná hodnota v nastaveniach ho prebije. Tuzemské odosielanie cez ePostman vyžaduje `0245` a skutočné desaťmiestne DIČ oboch strán; IČO ani IČ DPH ho nenahrádzajú.
- **Presety poštárov (CPDS)**: admin ich spravuje na `/admin/efaktura-cpds`; aktívne presety predvypĺňajú base URL v sprievodcovi a ich hosty sú dôveryhodné pre SSRF kontrolu
- **Viditeľnosť**: indikátor "Odošle sa ako eFaktúra" na formulári faktúry, "e" badge so stavom v zozname, preflight kontrola odberateľa pred odoslaním, readiness checklist pre SK platiteľov (aj pred globálnym zapnutím)

## Nastavenie (merchant) - sprievodca

Záložka **E-faktúra** v nastaveniach firmy (`eu_sk`; platitelia DPH vidia plný sprievodcu, neplatitelia a §7 / §7a variant „iba príjem" bez kroku auto-send a bez Peppol sender ID) je 3-krokový sprievodca:

1. **Choose the provider** - built-in ePostman production/test choices fill the fixed endpoint without an administrator preset. Registered custom providers and manual URLs remain available. Výber na [portáli Finančnej správy](https://www.financnasprava.sk) ostáva na merchantovi.
2. **Prepojte svoj účet** - `client_id` + `client_secret` od poštára a tlačidlo **Otestovať pripojenie** (jednorazový OAuth pokus, úspech sa uloží ako `efaktura_connection_tested_at`).
3. **Receiving settings** - save the selected connection after testing; local-first bridge synchronization failures remain visible across reload, including failed disabling. Manual polling requires successfully saved settings. Manual outgoing is available for ePostman on the outbound branch; automatic outgoing remains disabled there. Other providers retain their separately verified capabilities. Peppol participant ID je v "Rozšírené" - bežný merchant ho nerieši, derivuje sa z DIČ/IČO.

Serverový test pripojenia firmy je vyhradený vlastníkovi (support/admin zachovávajú existujúci privilegovaný prístup). Uložený `client_secret` sa doplní len pri nezmenenej URL poskytovateľa a `client_id`; pri ich zmene treba secret zadať znova. Local-first test bez firemnej routy používa iba credentials dodané klientom, nikdy nedopĺňa uložené credentials zdieľanej firmy.

U odberateľov SK stačí doplniť IČO/DIČ na kontakte (voliteľne explicitné **Peppol ID odberateľa**). Readiness checklist na zozname faktúr ukazuje počet kontaktov, ktorým údaje chýbajú.

Každý merchant si vyberá iného digitálneho poštára - **base URL musí byť per firma**, nie globálne.

## Globálna konfigurácia (ops)

```env
EFAKTURA_ENABLED=false
EFAKTURA_PROVIDER=sapi_sk
# EFAKTURA_SAPI_BASE_URL=  # voliteľný fallback pre lokálny dev; v produkcii nastavte per firma
# EFAKTURA_SAPI_SEND_DETAIL_PATH=/sapi/v1/document/send/{id}  # voliteľné; CPDS-špecifické sledovanie stavu
```

`EFAKTURA_ENABLED=false` je default - bez globálneho zapnutia sa gateway nebinduje na SAPI (ostáva noop) a v UI sa nezobrazí záložka E-faktúra ani panel na faktúre (`GET /api/config` → `efaktura_enabled`). Readiness checklist pre SK platiteľov sa zobrazuje aj pri vypnutom module (redukovaný "pripravte si kontakty" stav; termín povinnosti ide z `efaktura_mandatory_from` v `/api/config`).

Po zmene `.env`: `php artisan optimize:clear`

## Aktivácia (ops runbook)

1. Cez admin editor `/admin/efaktura-cpds` doplňte **overené** presety poštárov (RegWatch pravidlo: žiadne neoverené URL; per-preset `send_detail_path` s `{id}` placeholderom prebíja globálny).
2. Nastavte `EFAKTURA_ENABLED=true` (+ voliteľne `EFAKTURA_SAPI_ALLOWED_HOSTS`), potom `php artisan optimize:clear` a **reštart queue workerov** (config je v nich cachovaný).
3. `php artisan schedule:list` - overte registráciu `efaktura:poll-inbound` (15 min), `efaktura:sync-compliance-status` (30 min) a `efaktura:purge-inbound-inbox` (denne).
4. `php artisan efaktura:doctor` - globálny stav + per-company readiness (eligibilita, derivované Peppol ID, allowlist verdikt base URL, credentials, configured). `--company=<uuid>` obmedzí na jednu firmu, `--live` spraví reálnu SAPI-SK autentifikáciu.

### Sandbox E2E checklist (manuálne, pred produkčným zapnutím)

The ePostman token grant and actual cross-AP list/detail/XML/ACK for an Invoice and CreditNote have been verified in the test network; see the receiving runbook for exact transport and browser proof boundaries. Generic provider sending and production reporting remain separate verification requirements:

- [x] ePostman sandbox token grant through the isolated real client
- [ ] `document/send` happy path + akceptácia UBL validátorom poštára
- [ ] 422 recipient-not-found mapovanie ("Recipient is not registered in the Peppol network.")
- [ ] idempotency-key retry (opakovaný send toho istého dokumentu)
- [ ] status detail (`send_detail_path` daného CPDS) + `efaktura:sync-compliance-status`
- [x] ePostman sandbox inbound list/detail/acknowledge and verified original import (Invoice and CreditNote)

## Architektúra

```text
BusinessDocumentIssueService::issue()
  -> ComplianceSubmissionService::queueIfEligible()  [ak auto_send]
       -> SubmitBusinessDocumentCompliance (queue)
            -> SapiSkComplianceGateway::submit()
                 -> BusinessDocumentUblService::xml()
                 -> SapiSkClient::sendDocument()
                 -> business_document_compliance row
```

Provider compatibility: [`SapiSkClient`](../app/Services/Invoicing/Efaktura/SapiSkClient.php) uses the SAPI-SK routes and payloads implemented in Satflux. Compatibility with a particular CPDS must be verified; a provider's stated SAPI-SK support or entering its base URL does not establish compatibility.

## Fallback bez API

Merchants can download UBL/XML from the invoice detail. Manual upload is an option only if the selected provider accepts this format and its service performs the required delivery; that complete workflow has not been verified as a Satflux solution. Exporting a file or emailing a PDF does not prove that mandatory delivery has been completed.

## Inbound (Fáza B)

```text
efaktura:poll-inbound (scheduler každých 15 min, ak EFAKTURA_ENABLED)
  -> SapiSkClient list/detail/acknowledge
  -> UblExpenseDraftParser
  -> server mode:   BusinessExpense + UBL príloha na disku
  -> local-first:   efaktura_inbound_receipts ako INBOX položka
                    (inbox_status=pending, draft_json, ubl_encrypted, evolu_expense_id)
  -> efaktura_inbound_receipts (dedup by company + immutable connection provenance + provider document ID)
```

### Inbox pre local-first firmy

Local-first firma nemá serverové náklady, takže poller (`EfakturaInboundService`, vetva podľa `Company::usesServerInvoicing()`) uloží prijatý doklad ako **inbox položku** na bridge firme: rozparsovaný draft nákladu, UBL šifrované `Crypt`-om, stabilné `evolu_expense_id` (klient z neho odvodí Evolu id, takže druhé zariadenie neduplikuje) a denormalizovaný súhrn (dodávateľ, číslo, suma, mena). Acknowledge voči CPDS prebehne hneď pri polli (položka je bezpečne uložená).

Endpointy (`EnsureCompanyOwnership`, `{company}` = bridge firma):

| Metóda | Cesta | Účel |
|---|---|---|
| `GET` | `/companies/{company}/efaktura/inbox` | pending položky (súhrn + draft, bez UBL) |
| `GET` | `/companies/{company}/efaktura/inbox/{receipt}` | detail vrátane dešifrovaného UBL (pre prílohu) |
| `POST` | `/companies/{company}/efaktura/inbox/{receipt}/imported` | Confirm successful local original handoff with matching `original_sha256`; temporary server draft/XML are then cleared |
| `POST` | `/companies/{company}/efaktura/inbox/{receipt}/dismiss` | Reject dismissal while a staged original would be lost |

Credentials pre inbound si local-first klient uloží na bridge firmu cez existujúci `PATCH /companies/{company}/app-settings` (`efaktura_enabled`, `efaktura_inbound_enabled=true`, `efaktura_auto_send=false`, base URL, client id/secret) - žiadny samostatný subscription endpoint. Ručný poll: `POST /companies/{bridge}/efaktura/poll-inbound`.

**Local-first client:** inbound settings are mirrored to the company bridge for scheduled receiving. The live inbox lists pending documents; reconciliation must verify the exact original attachment and its company, not merely find an expense. Import uses stable expense/attachment identifiers, checkpoints and reads back the original bytes before confirming the original digest to the server. An oversized or failed attachment remains pending and downloadable. Destructive dismissal is unavailable while the only original is staged. See `efakturaInboxImport.ts` and its regression tests.

**Privacy and custody:** inbound credentials and temporary originals are processed server-side with server-held encryption keys; parsed summaries are not end-to-end encrypted. Pending originals are retained until verified local handoff, rather than deleted after the historical 60-day window. After successful import, encrypted local Evolu data and the provider archive hold the document; this does not add a permanent Satflux server archive. Provider archive retention/export and actual network receiving must be verified separately.

Peppol SMP lookup pred odoslaním rieši CPDS pri `POST /document/send` (422 ak príjemca nie je v sieti). Lokálna preflight kontrola overí, že kontakt má Peppol ID (IČO/DIČ/`peppol_participant_id`).

## UI (Fáza C)

- Záložka **E-faktúra** v nastaveniach firmy (`CompanySettingsForm`)
- Panel stavu, manuálne odoslanie a obnovenie stavu na `InvoiceShow` (vystavené faktúry a dobropisy, `eu_sk`)
- Peppol ID na kontakte odberateľa; manuálny inbound poll v nastaveniach E-faktúra (local-first cez bridge firmu); panel prijatých e-faktúr na stránke Náklady (local-first)

## Referencie

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md)
- [OpenPeppol Slovakia](https://peppol.org/learn-more/country-profiles/slovakia/)
- SAPI-SK špecifikácia: sapi-sk.sk (dobrovoľný štandard pre CPDS)

## Browser original custody handoff

The receiving inbox advertises `custody_protocol: post_ack_proof_v1`. A client
uses `POST .../efaktura/inbox/{receipt}/prepare-import` with `original_sha256`,
then verifies fresh local original bytes after that response before sending
`original_sha256` and the returned `custody_token` to `/imported` for completion.
Both requests preserve the existing company authorization and CSRF boundaries.
Without a token, `/imported` is a non-destructive legacy preparation. New clients
reject old/missing preparation rather than invoking destructive legacy behavior.

Staging is retained through preparation and until post-response proof permits
completion. Later intentional deletion after that custody transfer is the client's
responsibility. The token does not attest remote browser persistence. See the
[second-review contract](development/EPOSTMAN_PR441_REVIEW_ROUND2.md) for replay,
rollback, compatibility and test boundaries.
