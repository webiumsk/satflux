---
title: Connect with NWC (Nostr Wallet Connect)
category: wallet-connection
order: 6
meta_description: Connect your own Lightning node via a Nostr Wallet Connect pairing string, e.g. from Alby Hub.
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


# Connect with NWC (Nostr Wallet Connect)

**NWC** (Nostr Wallet Connect) lets you connect your own Lightning wallet or node through a pairing string - for example from a self-hosted **Alby Hub**. It is for advanced, self-hosting users.

## What you need

- A wallet that issues NWC connections (e.g. your own **Alby Hub**).
- An NWC pairing string that looks like:

```text
nostr+walletconnect://…?relay=…&secret=…
```

## Steps

1. In your NWC wallet (e.g. Alby Hub → Connections → Add Connection), create a connection and copy the `nostr+walletconnect://…` string.
2. In Satflux, open **Wallet connection → Advanced** and paste the string. Satflux detects it as NWC.
3. Save.

## Notes

- The server must have the **BTCPay Nostr plugin** available for NWC connections to work.
- NWC pairing strings from **Cashu ecash wallets** (e.g. Minibits) are not accepted here. To use such a wallet, set up [Cashu → Lightning](/documentation/cashu-lightning-settlement) instead - provide its mint URL and your Lightning address.
