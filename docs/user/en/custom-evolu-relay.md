---
title: Manage Evolu relays
category: security
order: 7
meta_description: Manage encrypted invoicing synchronization across multiple relays.
updated: 2026-11-07
---

# Manage Evolu relays

Satflux keeps invoicing data on your device and synchronizes encrypted changes through relays. The default list contains `wss://evolu.satflux.io` and `wss://free.evoluhq.com`. You can add other compatible relays, up to eight configured servers.

1. Open **Account settings > Evolu sync**. Each row shows the relay address and its current connection state.
2. Choose **Add relay**, enter a secure origin such as `wss://relay.example.com:8443`, and choose **Add relay**. Existing relays stay in the list.
3. Open a relay row to see its connection and turn **Use this relay** on or off. Default relays remain in the list and can be turned off. A custom relay can be removed after confirmation.
4. Before closing any Satflux tabs, save your [recovery phrase](/documentation/backup-and-recovery). On each device, sign in and load the updated settings, close **all Satflux tabs**, and reopen Satflux. This restarts an existing synchronization worker; reloading only one tab is insufficient.

Relay list changes apply to your account. **Disable sync on this device** pauses every relay only on the current device. Turning off or removing a relay keeps local data and does not erase data already stored on that server.

## Connection states

**Connected** means an actual synchronization WebSocket is open. **Connecting**, **Disconnected**, and **Connection error** describe that connection. **Unknown** means current connection evidence is unavailable; restarting all tabs may be necessary after an update. **Sync paused** means the local owner or current relay settings have not yet been confirmed. Sign in and load account settings while online; if local invoicing is locked, restore it with the correct recovery phrase. Disabled relays and device sync-off are shown separately.

An open connection does not prove protocol compatibility, successful writes, or a complete backup. The installed client requires a relay compatible with Evolu 7.x. Connections work independently, and missing history is reconciled on subscription or reconnect. Relays do not copy directly between themselves; a client synchronizes them. Allow time for a new relay to catch up.

Addresses must use `wss://`, with no credentials, path, query, or fragment. Duplicate addresses are rejected. Existing custom-only settings remain custom-only; use **Add default relays** to add the defaults deliberately. An installation that explicitly disables its default relay does not silently enable public defaults. Legacy HTTPS build addresses use the equivalent WSS connection; managing a list with an insecure WS/HTTP build default requires the administrator to configure a secure default first.

## Availability and recovery

The public `free.evoluhq.com` relay is a testing service and its data may be deleted. See the [Evolu relay documentation](https://www.evolu.dev/docs/relay). Keep an independent [data export](/documentation/backup-and-recovery) and your recovery phrase. **Relay recovery** requires the correct recovery phrase and an available copy of the data; the phrase itself contains no invoices. An **exported invoicing backup** can be restored into another account without the original recovery phrase. An encrypted backup requires the backup passphrase chosen for that file.

The local SQLite size describes this device, and relay usage statistics describe only the first enabled relay. Neither confirms a complete copy on every relay.
