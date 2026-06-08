# Slovak e-faktura (digitálny poštár) - príprava do 1.1.2027

Od **1. januára 2027** platí pre tuzemské B2B transakcie na Slovensku povinná štruktúrovaná elektronická fakturácia (zákon č. 385/2025 Z. z.). Dokumenty idú cez sieť **Peppol** a certifikovaného **digitálneho poštára** (CPDS). Finančná správa SR spravuje výber poskytovateľa a národné pravidlá (SK CIUS).

Satflux **nie je** digitálny poštár. Modul Business Invoicing generuje Peppol BIS Billing 3.0 UBL a voliteľne odosiela dokumenty cez **SAPI-SK** API, ak si merchant nastaví vlastné credentials u svojho CPDS.

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
- **SK CIUS** polia v UBL: Peppol scheme `0208` (IČO), `0245` (DIČ), `PartyLegalEntity`, `PaymentMeans`/IBAN, UN/ECE unit codes
- Per-company nastavenia v `app_settings` (credentials merchanta)
- Async odoslanie cez `SubmitBusinessDocumentCompliance` job (za `EFAKTURA_ENABLED=true`)
- SAPI-SK JSON `document/send` (metadata + UBL payload) podľa špecifikácie CPDS
- Inbound polling `efaktura:poll-inbound` - import UBL do **nákladov** + acknowledge
- API: `POST .../efaktura/send`, `POST .../efaktura/poll-inbound`, `GET .../efaktura/compliance`

## Nastavenie (merchant)

1. Na [portáli Finančnej správy](https://www.financnasprava.sk) vyberte digitálneho poštára pre firmu.
2. U poskytovateľa získajte **SAPI-SK** `client_id`, `client_secret` a **Peppol participant ID** (napr. `0245:2023980035`).
3. V profile firmy (`eu_sk`) v Satflux nastavte (API `PATCH .../app-settings`):
   - `efaktura_enabled: true`
   - `efaktura_sapi_base_url` - API endpoint vášho CPDS (napr. `https://dev.epostak.sk`)
   - `efaktura_peppol_participant_id`
   - `efaktura_sapi_client_id`
   - `efaktura_sapi_client_secret` (uložené encrypted)
   - `efaktura_auto_send: true` (voliteľné)
   - `efaktura_inbound_enabled: true` (prijímanie cez poll)
4. U odberateľov SK doplňte `peppol_participant_id` na kontakte (ak nie je IČO/DIČ).

Každý merchant si vyberá iného digitálneho poštára - **base URL musí byť per firma**, nie globálne.

## Globálna konfigurácia (ops)

```env
EFAKTURA_ENABLED=false
EFAKTURA_PROVIDER=sapi_sk
# EFAKTURA_SAPI_BASE_URL=  # voliteľný fallback pre lokálny dev; v produkcii nastavte per firma
```

`EFAKTURA_ENABLED=false` je default - bez globálneho zapnutia sa gateway nebinduje na SAPI (ostáva noop).

Po zmene `.env`: `php artisan optimize:clear`

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

Provider-agnostic vrstva: [`SapiSkClient`](../app/Services/Invoicing/Efaktura/SapiSkClient.php) implementuje štandard SAPI-SK; base URL smeruje na konkrétneho CPDS (ePošťák, Flowis, …).

## Fallback bez API

Merchant môže stiahnuť UBL/XML z detailu faktúry a nahrať do webového rozhrania poštára. PDF e-mailom **nie je** e-faktúra pre B2B od 2027.

## Inbound (Fáza B)

```text
efaktura:poll-inbound (scheduler každých 15 min, ak EFAKTURA_ENABLED)
  -> SapiSkClient list/detail/acknowledge
  -> UblExpenseDraftParser
  -> BusinessExpense + UBL príloha
  -> efaktura_inbound_receipts (dedup podľa providerDocumentId)
```

Peppol SMP lookup pred odoslaním rieši CPDS pri `POST /document/send` (422 ak príjemca nie je v sieti). Lokálna preflight kontrola overí, že kontakt má Peppol ID (IČO/DIČ/`peppol_participant_id`).

## UI (Fáza C)

- Záložka **E-faktúra** v nastaveniach firmy (`CompanySettingsForm`)
- Panel stavu a manuálne odoslanie na `InvoiceShow` (vystavené faktúry a dobropisy, `eu_sk`)

## Referencie

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md)
- [OpenPeppol Slovakia](https://peppol.org/learn-more/country-profiles/slovakia/)
- SAPI-SK špecifikácia: sapi-sk.sk (dobrovoľný štandard pre CPDS)
