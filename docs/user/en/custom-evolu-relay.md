---
title: Add your own Evolu relay
category: security
order: 7
meta_description: Synchronize encrypted invoicing data through your own relay alongside the default relay.
updated: 2026-11-06
---

# Add your own Evolu relay

In local-first invoicing, you can connect your own Evolu relay alongside the default relay. Your recovery phrase and encryption keys stay the same. Both relays receive encrypted data, including data for shared companies you have access to.

1. Open **Account settings > Evolu sync**.
2. Enter your relay's secure WebSocket origin, for example `wss://relay.example.com` or `wss://relay.example.com:8443`.
3. Keep the option to also use the default relay enabled and save. The page reloads to apply the connections.
4. Before closing any Satflux tabs, make sure you have saved your [recovery phrase](/documentation/backup-and-recovery). On each device, sign in and load the updated settings, then close **all Satflux tabs** and reopen Satflux. This restarts the existing synchronization worker; reloading only one tab is not sufficient.

The address must use `wss://` and contain no username, password, path, query or fragment. Your server must implement the Evolu protocol compatible with Satflux's installed Evolu 7.x client; an ordinary WebSocket server is insufficient.

Existing custom-relay settings continue to use that relay alone until you enable the additional-default option. Disabling that option uses only your custom relay. Clearing the custom address restores the configured default. If the installation disables its default relay, adding a custom relay does not enable a public fallback.

## Availability and recovery

The connections work independently: an unavailable relay does not turn off the other connection. Reconciliation happens when a connection subscribes or reconnects. Receiving data from one relay does not prove the other already has it. A newly added relay may need time to catch up.

The connection test checks reachability, not Evolu protocol compatibility or a complete backup. Usage statistics describe the primary custom relay when one is configured. They do not confirm that every relay holds a complete copy.

**Disable sync on this device** disables all relay connections on that device. Other devices keep their settings. Removing a relay stops future connections; it does not erase data already stored on that relay.

Keep your [recovery phrase](/documentation/backup-and-recovery) safe and make regular data exports. Recovery requires both the correct phrase and an available copy of the data.
