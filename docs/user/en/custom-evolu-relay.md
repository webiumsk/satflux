---
title: Manage Evolu relays
category: security
order: 7
meta_description: Manage encrypted invoicing synchronization across multiple relays.
updated: 2026-11-07
---

# Manage Evolu relays

Satflux keeps invoicing data on your device and synchronizes encrypted changes through relays. The default list contains `wss://evolu.satflux.io` and `wss://evolu.eu.freedomrelay.dev`. You can add other compatible relays, up to eight configured servers.

1. Open **Account settings > Evolu sync**. Each row shows the relay address and its current connection state.
2. Choose **Add relay**, enter a secure origin such as `wss://relay.example.com:8443`, and choose **Add relay**. Existing relays stay in the list.
3. Open a relay row to see its connection and turn **Use this relay** on or off. Default relays remain in the list and can be turned off. A custom relay can be removed after confirmation.
4. Before closing any Satflux tabs, save your [recovery phrase](/documentation/backup-and-recovery). On each device, sign in and load the updated settings, close **all Satflux tabs**, and reopen Satflux. This restarts an existing synchronization worker; reloading only one tab is insufficient.

Relay list changes apply to your account. **Disable sync on this device** pauses every relay only on the current device. Turning off or removing a relay keeps local data and does not erase data already stored on that server.

## Connection states

**Connected** means an actual synchronization WebSocket is open. **Connecting**, **Disconnected**, and **Connection error** describe that connection. **Unknown** means current connection evidence is unavailable; restarting all tabs may be necessary after an update. **Sync paused** means the local owner or current relay settings have not yet been confirmed. Sign in and load account settings while online; if local invoicing is locked, restore it with the correct recovery phrase. Disabled relays and device sync-off are shown separately.

**Connection** and **Synchronization** are shown separately. **Quota exceeded** means this relay rejected data because of its storage or service limit, even while the connection remains open. **Response received** means a protocol reply was observed; it does not confirm a complete backup or that previously rejected changes were replayed. After a provider increases a quota, restart all Satflux tabs as described above so the client reconciles pending history. The last recorded Evolu error in local statistics may be older and is not the current per-relay status.

An open connection does not prove protocol compatibility, successful writes, or a complete backup. The installed client requires a relay compatible with Evolu 7.x. Connections work independently, and missing history is reconciled on subscription or reconnect. Relays do not copy directly between themselves; a client synchronizes them. Allow time for a new relay to catch up.

Addresses must use `wss://`, with no credentials, path, query, or fragment. Duplicate addresses are rejected. Existing custom-only settings remain custom-only; use **Add default relays** to add the defaults deliberately. An installation that explicitly disables its default relay does not silently enable public defaults. Legacy HTTPS build addresses use the equivalent WSS connection; managing a list with an insecure WS/HTTP build default requires the administrator to configure a secure default first.

## Availability and recovery

Freedomrelay applies per-owner fair-use limits and offers no uptime guarantee. Its published terms do not specify a numeric storage quota; see [Freedomrelay](https://freedomrelay.dev/). The testing service `free.evoluhq.com` is no longer included by default; saved explicit relay selections remain unchanged. Data on that testing relay may be deleted; see the [Evolu relay documentation](https://www.evolu.dev/docs/relay). Keep an independent [data export](/documentation/backup-and-recovery) and your recovery phrase. **Relay recovery** requires the correct recovery phrase and an available copy of the data; the phrase itself contains no invoices. An **exported invoicing backup** can be restored into another account without the original recovery phrase. An encrypted backup requires the backup passphrase chosen for that file.

The local SQLite size describes this device, and relay usage statistics describe only the first enabled relay. Neither confirms a complete copy on every relay.
