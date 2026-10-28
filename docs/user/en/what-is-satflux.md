---
title: "What is Satflux"
category: getting-started
order: 1
meta_description: "Accept Bitcoin & Lightning with your own wallet. Non-custodial BTCPay control panel with multi-store, PoS, and Lightning (Lightning address / NWC)"
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


> When [temporary free PRO access](/documentation/free-pro-access) is active, registered non-guest accounts with a verified email can use PRO features without a subscription purchase. The usual limits and ownership permissions still apply. Check Pricing and Account for the current mode.

Short overview: non-custodial BTCPay control panel, multi-store, Lightning (Lightning address / NWC). Who it’s for.

Satflux (satflux.io) is a **non-custodial control panel** for **BTCPay Server**. It gives merchants a single place to manage one or more Bitcoin and Lightning stores. Satflux does not hold merchant funds; payments go to the connected wallet or address. Custody depends on the wallet or provider you choose.

**In short**

- **Non-custodial BTCPay control panel** - Satflux does not take custody of your funds; your chosen wallet or provider handles receiving.
- **Multi-store** - Run several stores (e-shops, physical shops, services) from one account and switch between them in one interface.
- **Lightning** - Choose a supported Lightning address or an NWC connection to your own node. Setup remains guided by the wallet checklist.
- **Who it’s for** - Shops, merchants, services, and e-shops that want to accept Bitcoin and Lightning with a receiving wallet or provider they choose.
- **Benefits for merchants** - One dashboard for stores and reporting; Point of Sale (PoS) apps for in-person or mobile use; invoice creation and tracking; CSV and (on higher plans) XLSX exports for accounting; optional Lightning Addresses (e.g. `you@satflux.org`) for tips or simple pay links; REST API and webhooks for integrations; no lock-in - you can move or self-host BTCPay later.

**Non-custodial in practice**

Satflux does not hold or control your funds. You connect a Lightning wallet or address from a provider you choose (a supported Lightning address or NWC). Custody depends on that wallet or provider: a supported Lightning address can use a custodial service. BTCPay Server generates invoices and receives payments; the money goes to the connected wallet or address. Satflux’s subscription and feature limits only affect management features (e.g. number of stores, exports, Lightning Addresses), not your ability to receive payments with your existing setup.

**Summary**

Satflux is the control panel and operations layer on top of BTCPay: multi-store, Lightning (a supported Lightning address or NWC), and tools for accepting Bitcoin and Lightning without Satflux holding your funds.
