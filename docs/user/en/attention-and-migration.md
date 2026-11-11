---
title: Needs attention and migration
category: invoicing
order: 35
meta_description: Review unresolved invoicing tasks and safely migrate SuperFaktura spreadsheet exports with their supplied originals.
---

# Needs attention and migration

## Review company tasks

Open **Needs attention** in your company's navigation. Payments, Sending,
Synchronization and Received documents collect issues with a reason and a link
to their existing resolution page. Refresh checks the available sources again.
It does not send a document, import an invoice or confirm a payment.

An unavailable source remains visible. A checked empty list only describes the
sources available to your permissions. Local payment decisions and sending
history are checked on this device; synchronization diagnostics concern the
device/account. Some receiving checks require the company owner.

## Migrate a spreadsheet export

In **Tools > Import**, choose SuperFaktura or a supported generic spreadsheet.
Export your issued invoices as a spreadsheet and select the destination company,
file, date format and column mapping. Existing ISDOC expense import remains in
the expense import flow. Other invoicing-provider adapters are not yet available.

The preview validates the entire supported file, shows row errors and duplicates,
and adds amounts, actual payments and balances separately for each currency.
Resolve conflicting invoice numbers, malformed values or unclear payment evidence
before confirming. A payment date alone cannot establish that an invoice was
fully paid. Missing payment dates are not invented. Existing financial records
are never silently overwritten.

You can also supply up to 50 original PDF/XML/ISDOC files. Each file is limited
to 10 MiB; the export and originals together are limited to 20 MiB. Selected files
must be retained and read back successfully before invoices are created. They
stay with your local invoicing data and encrypted synchronization. Download them
later from the import page, including after reopening the browser.

The archive retains the source spreadsheet and selected originals together as
a batch.

For SuperFaktúra, select an ISDOC file or a PDF containing an ISDOC
attachment. The wizard reads the items on your device and matches the invoice
number, seller, buyer, date, currency and total to the export and selected
company. Open the items in the preview before confirming. If the data cannot
be read or reconciled, check the warning and the affected originals. Where
there are no verified items, you can confirm a summary import. Use the
archived original PDF whenever you need the original presentation and
historical tax information.

The wizard keeps verified deductions as separate negative items when the line
amounts, VAT and invoice totals reconcile.

Review the result before retrying an interrupted import. Already completed exact
duplicates are skipped; changed records require review. Keep your recovery keys
and backups: local retention is not a new permanent server archive.

Update Satflux before restoring a backup containing retained originals. Older
versions refuse that backup format. Keep the original backup file unchanged.

If the wizard reports that the local storage update is not ready, close all
Satflux tabs in this browser and reopen the app. Build a new preview from the
same files. Completed exact duplicates are skipped and verified pending records
can continue. A storage confirmation error also shows the affected step.

A relay quota warning describes a synchronization limit. The importer can
continue once local writes and readback are verified. Check the affected relay
in the synchronization settings to resolve its storage or billing limit.

The company list displays verified local companies while relay synchronization
continues. Profile local statistics load without reading original files or
waiting for relay usage. The Refresh action can additionally calculate the
local database size. A pending recovery keeps its existing verification gate.


## Zaprite

Choose Zaprite in Tools > Import. Use a read-only API key from the source
organization, or a complete Zaprite JSON export downloaded from Satflux. The
key is used only for the read and is not saved. After loading, you can download
the source JSON before importing. Review item details, totals, payments and
duplicates, then confirm the import into the selected company.

The adapter supports selected fiat currencies with two decimal places and
confirmed payments with exact source evidence. BTC, LBTC and other currency
precisions require explicit exclusion. The preview shows excluded invoice
counts, and the complete JSON is archived even when invoices are excluded.
Excluded invoices do not become accounting records. The JSON is an API source
export, not an original invoice PDF. Drafts, unsupported adjustments, ambiguous
payments and conflicting invoice numbers must be reviewed in the source first.
