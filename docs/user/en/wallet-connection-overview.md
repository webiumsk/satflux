---
title: Wallet connection overview
category: wallet-connection
order: 1
meta_description: "Connect a supported Lightning address or use Advanced for connection strings and NWC. Boltz setup is paused."
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


# Wallet connection overview

Before your store can take payments it needs a wallet. Open **Wallet connection** on the store. Satflux never holds your funds - you connect **your own** wallet and all payments go straight to it.

You will find **Wallet connection** in the store, and the same setup runs during store creation.

## Two ways to connect

Satflux offers two active tabs. Most people use the first.

1. **Lightning address** (default, easiest) - paste one Lightning address like `you@wallet.com` and Satflux figures out the rest. See [Connect with any Lightning address](/documentation/connect-with-any-lightning-address).
2. **Advanced (connection strings)** - paste a supported Blink connection string, NWC pairing string or Lightning address. Descriptors are unavailable during the Boltz pause.

The built-in **wallet guide** in the app lists every supported wallet with step-by-step instructions.

## What Satflux stores

For a connection, Satflux keeps only what is needed to configure BTCPay, and any secret is **encrypted at rest**. When you view a connection later you see a **masked** value, and revealing it requires re-authentication. For SamRock/Aqua no private descriptor is stored in Satflux at all - your keys stay in Aqua; BTCPay only uses the watch-only descriptor. See [What we store and how it's protected](/documentation/what-we-store-and-how-its-protected).

## After you connect

Satflux tries to configure BTCPay automatically and marks the connection **connected**. If something needs attention it is flagged so support can help. Once the wallet is ready, the store unlocks and you can add Points of Sale, Pay Buttons, and Lightning addresses.

To change or disconnect later, see [Revoking or changing wallet credentials](/documentation/revoking-or-changing-wallet-credentials).
