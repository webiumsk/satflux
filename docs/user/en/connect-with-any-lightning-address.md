---
title: Connect with any Lightning address
category: wallet-connection
order: 2
meta_description: Point your store's Lightning backend at any you@wallet address - Blink, Blitz, Flash, Coinos, or any LUD-21 wallet.
---

# Connect with any Lightning address

Open **Wallet connection**, choose **Lightning address**, enter `you@wallet.com`, and connect. Satflux checks payment verification for the **specific address**, including Blink, Blitz, Flash and Coinos addresses. A wallet brand or shared domain does not guarantee LUD-21 support.

## Direct Lightning connection

If the address supports **LUD-21**, Satflux connects it through LNAddressConnect. An older Coinos address and an address migrated to Coinos v3 can have different capabilities even on `coinos.io`.

## Without LUD-21 - CashuMelt (beta)

If the address returns a valid Lightning invoice but has no payment-verification endpoint, Satflux checks whether CashuMelt is available for your store. When available, it shows the mint and asks for your explicit consent before saving. It preserves an existing mint selection; otherwise it offers the operator-configured default.

Cashu settlement depends on mint trust and fees. It verifies the Lightning payout to the address provider; it does not prove any later transfer inside that provider, such as delivery to an Ark balance. See [Cashu Lightning settlement](/documentation/cashu-lightning-settlement).

## If the address cannot be checked

A timeout, invalid response, missing plugin, or outstanding payment is shown as a retry or availability error. Satflux does not silently switch wallets. Changing an existing wallet still requires the usual confirmation and payment-safety checks; existing invoices keep their original payment settings.

You need only the Lightning address for this setup. For other connection methods, see [Choosing your wallet](/documentation/choosing-your-wallet). A receive address hosted by your store is a separate feature: [Store Lightning addresses](/documentation/store-lightning-addresses).
