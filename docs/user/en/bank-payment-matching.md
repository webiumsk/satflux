---
title: Bank payment matching
category: invoicing
order: 5
meta_description: Import bank statements and reconcile payments to your invoices automatically.
---

# Bank payment matching

Bank payment matching reconciles incoming payments against your issued documents, so you can see at a glance what has been paid.

## Import your transactions

Open **Payments** in your company and import a bank statement. Satflux understands common formats:

- **CSV** exports from your bank
- **CAMT** (ISO 20022) files
- **Wise** statements
- Notification e-mails from some banks (for example Slovak banks)

Duplicate transactions are detected and skipped, so re-importing an overlapping statement is safe.

## Bank notification emails

Use the address shown in **Payments > Import** for the selected company. In local invoicing, opening or refreshing Payments imports emails already processed on that same company’s server bridge into your encrypted device data. It does not retrieve missing notification history from the bank.

## Automatic matching

As transactions come in, Satflux matches them to invoices using the **variable symbol**, amount and other references. Matched invoices are marked paid; anything ambiguous is left for you to confirm or match by hand.

A newly imported outgoing bank email can also mark an existing expense paid when exactly one unpaid expense in the same company matches the full positive amount, currency and variable symbol. If the expense has no variable symbol, its document-number digits are used. Without a bank variable symbol, the payment reference must equal the complete document number. The bank booking date becomes the paid date. Ambiguous, partial and overpayments remain unmatched. This automatic expense matching applies to bank emails; CSV and Wise keep their existing behavior. Historical duplicate rows are not rematched on refresh; use the import batch’s automatic matching action to check them explicitly.

Newly imported emails whose recorded payment direction is unavailable cannot automatically pay an expense, including when their batch is matched again. Older saved movements retain their existing explicit batch-matching behavior.

## Expenses

Outgoing payments can be turned into **expenses** so your books stay complete for the accountant export.
