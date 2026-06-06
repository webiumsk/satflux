---
name: satflux-accounting
description: >-
  US LLC and EU merchant accounting context for satflux.io: VAT/OSS, sales tax,
  SK/CZ invoicing compliance, subscription billing, and legal document drafts.
  Use when working on business invoicing, pricing, tax lines, legal pages, or
  when the user mentions accounting, GDPR, Terms, Wyoming LLC, or e-fakturácia.
---

# Satflux accounting and compliance

**Not legal or tax advice.** Use this skill to align product copy, config, and code with documented satflux behavior. Flag gaps for a qualified accountant or lawyer.

## Operator model

| Entity | Role | Typical use in satflux |
|--------|------|------------------------|
| **Webium LLC** (Wyoming, US) | Operator of satflux.io; governing law in Terms | 30 N Gould St Ste N, Sheridan, WY 82801; EIN 61-2348057 |
| **EU/SK company** (merchant or billing) | VAT-registered seller to EU B2C/B2B | `jurisdiction: eu_sk` / `eu_cz` / `eu_other`; DPH on invoices when configured |

See `docs/SUBSCRIPTION_IMPLEMENTATION.md` - EU entity for EU B2C DPH; US LLC issues **sales tax** (manual rate), not SK/EU VAT on satflux subscriptions.

## Product map (business invoicing)

| Feature | Code / docs | Compliance note |
|---------|-------------|-----------------|
| Company jurisdictions | `companies.jurisdiction` | `eu_sk`, `eu_cz`, `eu_other`, `us` |
| Pay by Square QR | `PayBySquareGenerator`, EU PDF template | SK/CZ bank standard; not a substitute for e-fakturácia |
| Variable symbol | EU documents | Pairing with `BANK_PAYMENT_MATCHING.md` |
| BTC pay link | `payment_token`, `/pay/i/{token}` | Payment metadata only; not tax document |
| Subscription auto-invoice | `SubscriptionBillingInvoiceService`, `SUBSCRIPTION_BILLING_COMPANY_ID` | EUR from sats at settlement; one doc per BTCPay invoice id |
| ISDOC / e-fakturácia | Roadmap (`docs/BUSINESS_INVOICING.md` phases) | Do not claim full SK/CZ government integration until shipped |

## US LLC selling to EU (checklist for copy and billing)

When drafting Terms, Privacy, or invoice footers:

1. **Controller** - Webium LLC named as service operator; merchants are controllers for their customer PII on issued documents.
2. **VAT** - satflux subscriptions: if sold by US LLC without EU VAT registration, EU B2C may need OSS or local VAT (merchant responsibility to verify). If sold by EU billing company, show VAT where registered.
3. **Consumer rights** - EU/UK mandatory rights cannot be waived in Terms; Wyoming governing law plus EU carve-out (see `resources/js/content/legal/documents.ts`).
4. **GDPR** - Privacy policy: legal bases Art. 6, processors (hosting, email, BTCPay), transfers to US (SCCs), rights and `privacy@satflux.io`.
5. **Limitation of liability** - cap at 12 months fees or USD 100; "as is"; indirect damages excluded where permitted.
6. **Draft banner** - legal pages show draft notice until counsel signs off.

Legal content lives in `resources/js/content/legal/documents.ts` (EN/SK; ES falls back to EN body for most sections). UI chrome: `legal.*` in `locales/*.json`.

## Competitor positioning (landing)

Use `resources/js/constants/invoicingComparison.ts` and `landing.invoicing_section` i18n:

- **Satflux Pro** - BTCPay panel + EU PDF invoicing + BTC on issued invoices.
- **Zaprite** - global BTC invoicing; weak SK/CZ Pay by Square / e-fakturácia.
- **EU SaaS** (SuperFaktúra, Fakturoid) - strong local compliance; no BTCPay multi-store panel.

Keep comparison disclaimer; avoid absolute legal claims ("fully compliant") unless feature exists.

## When implementing tax or invoice changes

1. Read `config/invoicing.php`, `SubscriptionBillingInvoiceService`, company jurisdiction validators.
2. Never expose `btcpay_store_id` to frontend.
3. Test with `Http::fake()` for BTCPay; PHPUnit for billing and document actions.
4. Update `docs/BUSINESS_INVOICING.md` or `SUBSCRIPTION_IMPLEMENTATION.md` if behavior changes.
5. Ask user to confirm **legal entity name**, **registered address**, and **VAT/EIN IDs** before publishing final legal text.

## Questions to ask the user (accounting tasks)

- Which entity invoices satflux Pro today - Webium LLC or EU subsidiary?
- VAT OSS registered? Which member states?
- For SK/CZ merchants: target is PDF + Pay by Square only, or e-fakturácia integration next?
- DPA needed for Enterprise B2B customers?

## Related files

- `docs/BUSINESS_INVOICING.md`, `docs/BANK_PAYMENT_MATCHING.md`, `docs/SUBSCRIPTION_IMPLEMENTATION.md`
- `resources/js/pages/legal/`, `resources/js/components/landing/LandingInvoicingSection.vue`
- `config/pricing.php`, `app/Services/BtcPay/SubscriptionBillingInvoiceService.php`
