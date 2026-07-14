# Offline capabilities of the invoicing module (honest statement)

Audited 2026-07-12 as part of P1 (operational reliability). This document
states what the local-first invoicing module ACTUALLY does offline today -
it must not be advertised beyond this.

## What works offline (already loaded SPA, Evolu bound)

The local Evolu SQLite database works fully offline. With the app already
loaded in a tab and the recovery phrase unlocked:

- browsing companies, contacts, documents, expenses, stock, bank records,
- creating and editing DRAFTS of any document type,
- editing contacts/companies/settings (stored locally, synced later),
- local bulk operations (mark paid, cancel, delete) and CSV export,
- attachments (up to 384 KB per file, stored in Evolu).

Changes made offline are CRDT mutations that upload automatically once the
relay WebSocket reconnects; nothing is lost by going offline mid-work.

## What requires the server (online)

- ISSUING any document - the number allocator reserves atomically on the
  server (no local fallback, by design; audit F3). Offline the document
  stays a draft and the form says an internet connection is needed.
- PDF / ISDOC / UBL rendering and e-mail sending (ephemeral bridge).
- ISDOC extraction, e-faktura, BTCPay checkout, WooCommerce inbox.
- Server number-series preview (falls back to a local preview offline).
- Authentication (login, session refresh).

## What does NOT work: cold start offline

The app is NOT installable and has no service worker. After a browser
restart with no network:

- the SPA assets themselves cannot load from the server,
- the router guard waits for `fetchUser()`, which fails and redirects to
  the login screen.

So a cold offline start does not reach the local data even though it is
present on the device. Making this work needs a service worker + cached
auth strategy - tracked as a P2 project, deliberately out of P1 scope.

## In-app signals

- `OfflineNoticeBanner` (invoicing hub) appears when the browser goes
  offline and names the above split.
- The relay sync indicator (P1 phase 4) shows `unreachable` while offline;
  changes stay local and upload on reconnect.
- Issue attempts offline fail fast with `invoicing.issue_requires_online`.
- Deleting an ISSUED invoice offline fails fast too
  (`invoicing.delete_requires_online`): gapless numbering (P3) frees the
  number on the server allocator before the local delete, so the sequence
  hands the same number out again instead of keeping a hole. Only the most
  recent invoice of a series can be deleted; older ones are refused.
