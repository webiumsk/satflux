---
title: Connect Blink
category: wallet-connection
order: 5
meta_description: Connect the Blink wallet to your store - the fast, non-custodial receive-only address, or the legacy API key.
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


# Connect Blink

**Blink** is a quick way to start receiving Lightning. There are two ways to connect it.

## Recommended: your Blink Lightning address (non-custodial, receive-only)

Just paste your Blink address `you@blink.sv` on the **Lightning address** tab of Wallet connection. This is **receive-only** - Satflux and BTCPay can watch for and receive payments, but cannot send or move your funds. Nothing else to configure.

## Legacy: Blink API key connection string

Blink also supports a full connection string with an API key:

```text
type=blink;server=…;api-key=blink_…;wallet-id=…;
```

You get the API key in the **Blink dashboard → API Keys** (create a key with read + receive permissions). Paste the string on the **Advanced** tab.

> This API-key connection is treated as **EU-legacy**. Satflux may show a migration notice suggesting you move to the non-custodial `@blink.sv` address instead. Blink is not being removed - the address form is simply the recommended, simpler option.

## Which should I use?

- Want the simplest start? Use the **`@blink.sv` address**.
- Already have an API key set up? It still works.

See [We cannot access or move your funds](/documentation/we-cannot-access-or-move-your-funds) for exactly what "receive-only" means.
