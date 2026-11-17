---
title: Import expenses from SuperFaktura
category: invoicing
order: 12
updated: 2026-11-17
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

## Supplier contact and IBAN

In a new expense, optionally select **Supplier contact**. Its IBAN is copied into **Supplier IBAN**. If the contact has no IBAN, an account safely extracted from the same supplier's PDF/XML is used; otherwise the field stays empty. You can also enter the supplier name and IBAN manually. A conflicting supplier does not reuse another supplier's extracted account.

Saved expenses retain their supplier name and account when a contact later changes. Manual edits and cleared accounts survive subsequent extraction; use the expense editor to deliberately change them. Internal note is for notes and does not provide the QR recipient.

## Payment QR codes

An unpaid EUR expense with a positive amount, supplier name, valid supplier IBAN and a variable symbol of up to 10 digits offers **Payment QR** in its row. Choose **PAY by square** or **PayMe**. Check the recipient, exact amount and variable symbol in your bank app. Generating or scanning the QR does not mark the expense paid. Larger exact amounts unsupported by PayMe can use PAY by square.

No IBAN means no QR. Paid/cancelled expenses, bank conflicts, negative credit notes and imports requiring payment review have no payable QR. Selecting a contact cannot override partial/prepaid or ambiguous imported payment evidence. Old ISDOC/Peppol records without retained financial evidence remain blocked. Historical supplier lines in notes are not copied into payment fields.

## Add a received credit note manually

In **Expenses**, choose **New received credit note**, or select **Received credit note** as the document type in the new expense form. Enter its own document number and variable symbol, supplier details and a **negative total**, then save. Received credits remain in Expenses, carry a label and reduce signed expense totals. They do not generate outgoing-payment QR codes or match debit expense payments.

The expense detail also offers **Add received credit note**. It prefills supplier details and a negative amount in a new form, leaving the new document number and variable symbol empty. This does not create a permanent link to the original expense. Issued customer credit notes remain under Documents > Credit notes.
