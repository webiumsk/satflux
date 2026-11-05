---
title: Import expenses from SuperFaktura
category: invoicing
order: 12
updated: 2026-11-03
---

# Import expenses from SuperFaktura

Open your company’s **Tools > Import**, select **Expenses**, then choose **Import expenses**. You can also use **Expenses > Import**.

1. Select your SuperFaktura XLSX export, or an XLS/CSV file.
2. Review the suggested column mapping. Issue date and total are required.
3. Start the import and check the imported/skipped summary. Skipped rows show a reason.
4. Choose **View expenses** to see records from all years.

The current 33-column SuperFaktura export is recognized automatically. Original internal/document numbers, dates, totals and symbols are preserved. Supplier, category, payment and item details go into the internal note; the import does not create supplier contacts or separate expense items.

A dated payment with sufficient amount in the expense currency marks the expense paid. Partial and foreign-currency payments remain unpaid for review, with their original payment details retained. Older files containing only a payment date retain the existing paid behavior.

Existing internal numbers are skipped within the selected company. Rows without an internal number receive a new number; importing those rows again creates new expenses.

Limits: **10 MB, 2,000 rows, 256 columns** per file. Invalid or overlong rows are skipped without truncation. Spreadsheet formulas are treated as text.

In local-first invoicing, the spreadsheet is processed in your browser and saved with the existing encrypted invoicing data. Keep your recovery phrase backed up. PDF attachments are separate from spreadsheet import.

## Defaults for a new expense

The variable symbol follows the digits in the document number, including leading zeros. The delivery date follows the received date. Editing either default manually stops that link. Explicit values extracted from an attachment are preserved; existing expenses keep their saved values. A manually cleared delivery date stays blank after saving and reopening.

If the document number contains more than 32 digits, the automatic variable symbol stays blank and the document number is kept. Shortening the number restores the default until the symbol is edited manually.

Local expense changes wait for ongoing bank matching before they are saved. If the browser cannot coordinate changes, reload the tab or use an up-to-date browser. If a result cannot be confirmed, reload and check the saved record before another attempt. Later deliberate edits to a paid expense keep its existing payment status.

Unlink a bank payment before changing the linked expense amount or currency. Offline matches are confirmed again after synchronization. If a link needs review, Payments and the expense show a warning; unlink the unresolved bank link before matching again. Other matches and separately recorded payments are preserved. Use up-to-date clients on every device.

## Expense list summary

The summary below **Expenses** covers every document matching the current filters, across all pages. It shows the count, total and unpaid expense total. Selecting rows does not change it. Each currency has its own totals.

The unpaid expense total adds the full amounts of documents not marked paid. Partial payments retained in import notes are not automatically deducted; review those documents before treating this figure as an outstanding balance.

## PDF attachments on an individual expense

Open an individual expense and add its PDF. In local-first invoicing, attachments and extracted expense fields are saved with your encrypted local data. Each local attachment may be at most **384 KiB**. In the create or edit form, choose **Save** to save pending attachments. On the expense detail page, attachments are saved immediately after you finish or skip optional ISDOC processing.

You can allow temporary server processing to detect embedded ISDOC data. Cancel keeps the attachment local. If ISDOC is found, confirm field extraction separately or keep only the attachment. Extraction uses your existing allowance and credits; it is not OCR. When the allowance is exhausted, you can still attach the file and enter its fields manually.
