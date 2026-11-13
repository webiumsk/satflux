---
title: Invoice deductions, buyer details and payments
category: invoicing
order: 5
updated: 2026-11-13
---

# Invoice deductions, buyer details and payments

Invoice details keep deductions, buyer identity and payment evidence together.

## Deduction items

For a deduction on a Slovak or Czech invoice, enter a negative quantity and a positive unit price on a separate manual line, for example `-1 x 15.00`. The invoice total must remain positive. Use the applicable VAT rate; the line and its VAT are subtracted. A deduction cannot reference a stock item or warehouse. Disconnect an existing stock line before using it as a deduction.

Negative-quantity deductions at positive prices support PDF, email, UBL/ISDOC export and eFaktúra preparation under the existing sending conditions. A negative unit price remains unsupported by structured exports. German hybrid invoices and external US tax providers remain outside this deduction flow.

Credit notes created from these invoices retain the deduction without changing the original invoice. Combining quantity deductions with separate percentage discounts remains unsupported in structured exports.

## Buyer identifiers

When a contact contains a registration number, tax number or VAT ID, the invoice preview and PDF display them. Issued invoices retain their saved buyer identity. Editing a contact later does not update an issued invoice.

WooCommerce imports supported buyer identifiers from the order. Empty order fields preserve identifiers already saved on the contact. A different checkout extension may need an additional field mapping.

A PDF sent automatically before browser import can include only buyer data available to the server from the order. Browser-only contact identifiers cannot be added to an already sent document.

## Recorded payment method

Payment options offered to a customer are separate from the recorded payment method. Paid WooCommerce orders retain the supplied gateway classification; matched bank payments show their bank source, and verified BTCPay payments show BTCPay. Manual payments are labelled manual. Older invoices without recorded evidence show an unspecified method; their history is not guessed or rewritten.
