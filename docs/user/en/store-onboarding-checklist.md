---
title: "Store onboarding checklist"
category: getting-started
order: 5
meta_description: "Complete supported wallet setup, create a Point of Sale and verify payment arrival."
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


After you create a store, a **wallet onboarding checklist** helps you finish wallet setup and confirm that the store can receive payments. The steps are the **same** for supported wallet connections: you enter your credential, create a Point of Sale (PoS) app, and test a payment. You work through the list and mark steps complete yourself; the checklist is a guide, not an automatic check.

---

**What the checklist is**

The steps are:

1. **Enter your wallet credential** - In **Wallet connection** (store → LN Wallet Connection), add a supported Lightning address or connection string. All configuration is done in the Satflux panel; you do not need to enable plugins or configure BTCPay yourself.
2. **Create a Point of Sale (PoS)** - Create a PoS app for the store. You can use it to create invoices and accept in-person or online payments.
3. **Test a payment** - Create a test invoice (e.g. from the PoS), pay it **from a different wallet** than the one that receives (e.g. from another phone or app), and confirm that the funds arrive in your receiving wallet.

When testing, you must pay from a **different** wallet than the one connected to the store; otherwise you are not really testing the flow. Once the payment shows up in your wallet, you can mark the step complete.

---

**Where to find it in the UI**

- **After creating a store** - You are taken to the **Next steps** page. Use the link **“View Onboarding Checklist”** to open the checklist for that store.

- **From the store** - Open the store and go to the **Wallet onboarding** / **Checklist** page. The URL is: /stores/{store-id}/checklist.

The checklist page is titled **“Wallet onboarding”**. You can tick off each step when you have done it; progress is saved. All steps are required for a complete setup.

For how to add your credential, see Wallet connection overview. For supported options, see Choosing your wallet.
