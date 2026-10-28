---
title: "Aqua and Bull descriptor connections are paused"
category: wallet-connection
order: 4
meta_description: "Boltz receiving connections are paused"
updated: 2026-10-28
---

# Aqua and Bull descriptor connections are paused

New Aqua/Bull Bitcoin descriptor connections and Aqua SamRock pairing are paused in Satflux because our Boltz receiving integration is unavailable. Bull mobile Lightning has resumed, but its replacement service is not a supported Satflux backend. Aqua currently supports Lightning sending through Indra, while receiving is unavailable. Instructions for these old connections do not enable new setup.

Use a [supported Lightning address](/documentation/connect-with-any-lightning-address) or [NWC from your own node](/documentation/connect-with-nwc). Keep an already configured Bitcoin on-chain wallet available where applicable.

## Existing connections

For an existing Boltz store, open **Wallet connection**, choose **Change connection**, complete the existing confirmation and select another supported receiving wallet. Review pending payments and recovery with support before replacing a connection. Existing wallet records, invoice history and recovery are preserved.

Availability checked on 2026-10-28.
