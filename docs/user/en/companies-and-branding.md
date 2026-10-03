---
title: Companies and branding
category: invoicing
order: 2
meta_description: Set up your company details, logo, numbering and bank account.
---

# Companies and branding

A **company** holds the business details that appear on your documents. You can have more than one company (subject to your plan) - for example a sole trader and a limited company.

## Company profile

Open your company's **profile** settings and fill in:

- **Legal name and trade name**
- **Address** (street, city, postal code, country)
- **Tax identifiers** - registration number, tax ID and VAT number as they apply in your country
- **Bank account** (IBAN/BIC) for payment instructions on invoices

Your jurisdiction (country) drives country-specific rules such as VAT handling and, in Slovakia, e-invoicing.

## Branding

On the profile's **Logo & signature** tab you can upload your **logo** and an optional **signature/stamp** image. These appear on your generated PDFs.

## Numbering

Under **App settings → Numbering** you define number series for each document type (invoices, proformas, credit notes, and so on), including the format and how they reset (yearly, monthly, never). Satflux allocates the next number for you when you issue a document.

- **Format:** `YYYY` is the year, `MM` the month, a run of `N` (at least two) is the counter, everything else is literal text - e.g. `INVYYYYNNNN` gives `INV20260001`.
- **Reset:** a yearly series starts again at 1 with the first document of a new year, a monthly one with each new month. The year and month follow your device's calendar date at the moment you issue.
- **Next number (last used):** the counter the next document continues from in the current period. Use it when you move from another system mid-year - e.g. enter `120` and the next invoice gets `…0121`. Satflux never goes below a number that is already used.
- **Issuing needs a connection:** the number is reserved on the server, so two devices (or colleagues sharing a company) can never get the same number. Offline, the document stays a draft.

### Deleting numbered documents

Only the **latest numbered document** of a series (issued, paid or cancelled) can be deleted, and its number is then given to the next document - the series stays without gaps. Delete newer documents first to go further back. An older document can only be **cancelled**. Documents with a matched bank payment or a linked document (e.g. a credit note) cannot be deleted. Drafts can always be deleted.

## Next step

Add your customers in [Contacts](/documentation/contacts).
