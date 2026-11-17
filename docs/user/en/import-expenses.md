---
title: Import expenses from SuperFaktura
category: invoicing
order: 12
updated: 2026-11-16
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


## Search and filter expenses

Use the status buttons and issue-date period to narrow the list. The initial period is the current year; choose all dates, a preset or your own date bounds. Open **More** to filter the amount, due date and text. Search covers internal and supplier document numbers, title, variable symbol and notes.

Choose **Filter** to commit advanced fields. On mobile, use the filter drawer. **Clear filters** (on mobile **Clear all**) removes the search and other criteria. Changing applied filters clears the current selection. Totals and **select all** use the entire filtered result, including records on other pages.

## Defaults for a new expense

The variable symbol follows the digits in the document number, including leading zeros. The delivery date follows the received date. Editing either default manually stops that link. Explicit values extracted from an attachment are preserved; existing expenses keep their saved values. A manually cleared delivery date stays blank after saving and reopening.

If the document number contains more than 32 digits, the automatic variable symbol stays blank and the document number is kept. Shortening the number restores the default until the symbol is edited manually.

Local expense changes wait for ongoing bank matching before they are saved. If the browser cannot coordinate changes, reload the tab or use an up-to-date browser. If a result cannot be confirmed, reload and check the saved record before another attempt. Later deliberate edits to a paid expense keep its existing payment status.

Unlink a bank payment before changing the linked expense amount or currency. Offline matches are confirmed again after synchronization. If a link needs review, Payments and the expense show a warning; unlink the unresolved bank link before matching again. Other matches and separately recorded payments are preserved. Use up-to-date clients on every device.

## Expense list summary

The summary below **Expenses** covers every document matching the current filters, across all pages. It shows the count, total and unpaid expense total. Selecting rows does not change it. Each currency has its own totals.

The unpaid expense total adds the full amounts of documents not marked paid. Partial payments retained in import notes are not automatically deducted; review those documents before treating this figure as an outstanding balance.

## PDF attachments on an individual expense

Open an individual expense and add its PDF. In local-first invoicing, attachments and extracted expense fields are saved with your encrypted local data. Each local attachment may be at most **10 MiB**. In the create or edit form, choose **Save** to save pending attachments. On the expense detail page, attachments are saved immediately after you finish or skip optional ISDOC processing.

You can allow temporary server processing to detect embedded ISDOC data. Cancel keeps the attachment local. If ISDOC is found, confirm field extraction separately or keep only the attachment. Extraction uses your existing allowance and credits; it is not OCR. When the allowance is exhausted, you can still attach the file and enter its fields manually.

If saving a larger attachment is interrupted, retry while the file is still selected. After reloading, remove the incomplete attachment and select the original file again. Complete the upload or remove the incomplete attachment before creating a full backup.

## Payment QR codes

For an unpaid EUR expense with a supplier name, a valid supplier IBAN and a variable symbol of up to 10 digits, choose **Payment QR** in its row. The amount must be greater than zero with at most 12 digits before the decimal point, and the supplier name must be at most 70 characters. Switch between **PAY by square** and **PayMe**, and check the recipient, amount and variable symbol before confirming in your banking app. The code is generated in your browser. Opening PayMe deliberately opens its payment link. Scanning a code does not mark the expense as paid. For amounts that PayMe cannot represent, use PAY by square; the amount is never rounded.

No code appears for paid or cancelled expenses, disputed bank matches, or imports with partial or unresolved payments. Supplier payment details are taken from explicit imported supplier metadata; the company’s own account is never used. New eligible e-invoice and ISDOC imports retain the supplier account; older imports without that metadata must first have their payment details completed.

When extraction has no payment reference, the variable symbol stays blank and the supplier bank account is not imported. Verify the original document and enter the correct variable symbol. Payment QR remains unavailable until the supplier name and IBAN are also complete and verified.

To complete verified details, edit the expense, enter the variable symbol in its field and add or replace these two lines in **Internal note**. Keep exactly one line with each label, including the English labels shown here: Before saving, also verify that the saved amount and currency match the full unpaid payment instruction exactly, with no more than two decimal places.

```text
Supplier: VERIFIED_NAME
Supplier bank account: VERIFIED_IBAN
```

Replace `VERIFIED_NAME` and `VERIFIED_IBAN` with the supplier name and IBAN verified against the original document. Preserve other notes and payment information, then save and return to the list. These steps also apply when extraction omitted the account; the eligibility conditions above still apply.
