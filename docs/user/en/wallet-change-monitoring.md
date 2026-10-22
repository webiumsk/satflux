---
title: "Wallet change blocked by invoice monitoring"
category: wallet-connection
order: 8
meta_description: "Why a new store can have an automatic wallet-check invoice and when it is safe to retry a wallet change."
---

Satflux can create a small Lightning invoice automatically when verifying the
receiving wallet. This can happen before you create your first customer invoice.
The check is not intended to be paid and is archived after reading it.

**Archiving does not end monitoring or cancel a Lightning payment request.**
The wallet cannot be replaced while an invoice is still monitored or processing.

New wallet checks request one minute of checkout validity and one additional
minute of monitoring. Older checks can retain the store's longer monitoring
period, normally about a day. The actual BTCPay deadline and payment status take
precedence; there is no guaranteed two-minute release for every store.

Open the affected store's invoices in BTCPay and include archived invoices.
Inspect status and monitoring expiration if displayed. Wait for monitoring to
finish, then retry the wallet change. Processing payments, unresolved wallet
updates or Cashu recovery can keep it blocked longer. Contact support if the
reason remains unclear; do not delete records or mark invoices paid to bypass it.

Send support only the count, statuses and deadlines. Keep payment requests,
checkout links, identifiers, credentials and customer data private.
