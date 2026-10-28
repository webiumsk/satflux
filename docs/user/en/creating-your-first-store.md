---
title: "Creating your first store"
category: getting-started
order: 3
meta_description: "Create a store, choose a supported receiving wallet and complete setup. Boltz descriptor and SamRock connections are paused."
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


You create a new store in Satflux in three steps: basic info, wallet type, and confirmation. The store is then created in BTCPay Server and a short onboarding checklist is shown so you can finish wallet setup and test a payment.

**Step 1 - Basic info**

- **Store name** - A label for your store (e.g. “Main shop”, “Online store”). Guest accounts get their first store created automatically as `My Store - XXXXXXXX` (a unique suffix); you can rename it any time in **Store settings**.

- **Default currency** - The currency used for prices and amounts (e.g. EUR, USD, BTC, SATS).

- **Timezone** - Used for invoices and reports.

- **Preferred price source** - The exchange or source used to convert fiat to BTC (e.g. for displaying amounts). A sensible default is chosen based on your default currency; you can change it here or later in store settings.

Click **Next step** when all fields are filled.

**Step 2 - Choose wallet type**

Choose how this store will receive Lightning (and on-chain) payments:

- **Blink** - Fast setup, good for getting started quickly. You will connect your Blink wallet (e.g. via connection string) in this step or later. See Choosing your wallet for supported options.

- **Other supported connections** - Use a Lightning address or an NWC connection from your own node. Aqua/Bull descriptor and SamRock setup are paused.

You can configure a supported receiving connection in this step or finish it later in **Wallet connection**. Descriptors and SamRock are unavailable during the Boltz pause.

Click **Back** to change basic info, or **Next step** to continue.

**Step 3 - Confirm**

Review store name, currency, timezone, price source, and wallet type. Click **Create store** to create the store in BTCPay. You are then redirected to the “Next steps” page with a link to the **wallet onboarding checklist** (e.g. connect wallet, enable Lightning, test an invoice).

**After creation**

- Finish wallet setup and Lightning in **Wallet connection** if you did not do it in step 2. See Wallet connection for step-by-step instructions.

- Create a **Point of Sale (PoS)** app and, if you want, a **Lightning Address** from the store dashboard.
