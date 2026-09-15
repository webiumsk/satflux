---
title: SEPA Instant QR (euro bank payments)
category: payments
order: 5
meta_description: Accept euro bank transfers via a SEPA Instant QR code, alongside Bitcoin.
---

# SEPA Instant QR (euro bank payments)

**SEPA Instant QR** lets you accept **euro bank transfers** at checkout: the customer scans a QR code with their banking app and sends a SEPA Instant payment. It sits alongside your Bitcoin and Lightning options.

## Set it up

1. Open your store and go to **SEPA Instant QR**.
2. Enter your bank details (the account that should receive the euro transfers) and enable the method.
3. Optionally set up e-mail confirmation of incoming payments so the store can reconcile them.

## Where is my payment

Every awaiting or needs-review payment whose reference starts with `QR-` has a **Where is my payment** button. It asks the public diagnostics service of the Slovak Financial Administration's instant payment notifier (NOP) what it knows about that reference and shows the timeline: transaction id created, bank notification stored, matched to the cash register, published, received.

What to expect:

- **NOP knows this id** appears for stores that confirm through NOP with an eKasa certificate, and for payments a notification-enabled bank account (Tatra banka, SLSP) reported.
- **NOP has not seen this id** is the normal answer for stores that confirm manually, through Fio or through e-mail: their references are generated locally, so NOP has nothing to show even when the money has already arrived (and even when Fio or e-mail confirmation has already settled the invoice). It does not mean the customer has not paid - check your bank account.
- The timeline never says which account was credited. Always check the transfer in your banking app before marking a payment as paid - satflux does not confirm anything from this screen.

The same data is on [kdejemojaplatba.sk](https://www.kdejemojaplatba.sk/), an independent viewer of the same service.

## Notes

- Available to all accounts, including guests.
- Payments land in **your bank account** - this is a euro rail you control, separate from Bitcoin.
- Pairs well with [invoicing](/documentation/getting-started-with-invoicing) and [bank payment matching](/documentation/bank-payment-matching), which can reconcile incoming bank payments to your invoices.
