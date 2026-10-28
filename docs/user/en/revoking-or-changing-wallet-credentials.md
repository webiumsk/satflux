---
title: "Revoking or changing wallet credentials"
category: wallet-connection
order: 7
meta_description: "Safely replace a supported wallet connection and move away from paused Boltz receiving."
updated: 2026-10-28
---

> **Boltz receiving connections are paused.** New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.
>
> Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.


You can **change** your wallet credential (a supported connection string or Lightning address) at any time by submitting a new one in the Satflux panel. We do not offer a “disconnect” action: in BTCPay you can only **replace** the connection with a new credential, not remove it without replacing. So to stop using a wallet you revoke or change it in the wallet app, then connect the store to a **new** credential.

---

**Confirming a change of a connected wallet**

Replacing a wallet that is already **connected** is a sensitive change, so Satflux asks you to confirm it before the form opens:

1. Open the store → **Wallet connection** and press **Change connection**.
2. Enter your account password (accounts that sign in with a recovery phrase skip this step).
3. We email a **6-digit code** to your account address. Enter it in the form - the code expires after 10 minutes and you can request a new one after a short countdown.
4. The form opens with the current credential filled in. Replace it and save. The confirmation is valid for 15 minutes; if it runs out, Satflux asks for a new code before saving.

Nothing on your store changes until the code is confirmed, and the same confirmation covers switching a Lightning store to Cashu. A **guest account** has no email to receive the code, so it must first be upgraded with an email address (see [Create your account](/documentation/create-account)). The first wallet connection of a new store needs no code.

---

**Blink: revoke or rotate the API key, then update in Satflux**

1. **In the Blink dashboard** 1: Revoke (delete) the old API key and/or create a new one with **read and receive** only.

1. **In Satflux:** Open the store → **Wallet connection** (LN Wallet Connection). In the form, **replace** the old connection string with a new one: type=blink;server=...;api-key=NEW_KEY;wallet-id=.... Save.

1. **In BTCPay:** The connection is the one currently configured for the store. We do not offer “disconnect”; you **change** it by submitting the new connection string in Satflux. Support may need to apply the new string in BTCPay if your setup uses a manual step. After that, the store uses the new key.

So: revoke or rotate in Blink, then reconnect the store with a new credential in Satflux (and BTCPay if applicable).

---

**Existing Aqua/Bull through Boltz: choose another supported connection**

New descriptors and SamRock pairing are paused. Review outstanding payments and recovery, then use **Change connection** to select a supported Lightning address or NWC wallet. Re-exporting a descriptor does not restore the suspended provider.

---

**Summary**

- We do **not** offer “disconnect” in Satflux or in BTCPay - only **change/replace** the connection.

- To stop using a credential: review pending payments, then in Satflux choose another **supported** connection so the store uses the new credential.

- The store always has one active wallet connection; you change it by replacing it with another.

For Blink key format and rotation, see Blink: connection string format and troubleshooting. For where to paste the credential, see Wallet connection overview.
