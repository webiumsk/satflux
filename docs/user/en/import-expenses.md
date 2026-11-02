---
title: Import expenses from SuperFaktura
category: invoicing
order: 12
updated: 2026-11-02
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
