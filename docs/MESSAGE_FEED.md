# Message feed behavior

The authenticated `/api/messages` feed merges Satflux-local messages and the user's BTCPay notifications in descending creation-time order. Security messages retain their red label and the existing global unread-security banner; they are no longer pinned ahead of newer notifications.

## Filters and continuation

`category` accepts `all` (default), `critical` (local security/critical alerts), `invoices`, or `other`. `unread=true` combines with any category. `per_page` is between 1 and 100, default 20. Filters apply before pagination. Every unread local message remains eligible; the existing 30-day retention window for read local messages remains.

The first request omits `cursor`. Continue with `meta.next_cursor` while `meta.has_more` is true, retaining the same category, unread filter and page size. Cursors are encrypted, authenticated, bound to the user and filters, and expire after one hour. A refresh starts from the newest messages. `page` alone cannot request arbitrary later pages. The UI keeps previous cursors for back navigation.

BTCPay supports offsets and seen-state filtering but has no notification category filter. A request scans at most five batches of 100 upstream rows. A sparse filter can therefore return a short or empty page with `meta.scan_limited=true` and a continuation cursor. The UI offers to continue searching older messages; this is not the end of history. Local messages older than the scanned upstream frontier wait for continuation, preserving chronological order. `meta.total` is a lower-bound pagination hint until `meta.total_is_exact` is true.

Local continuation uses a `(created_at, id)` keyset so marking unread messages read does not skip subsequent local rows. Upstream continuation scans both read and unread notifications, applying unread filtering locally so read actions do not change upstream offsets. New upstream notifications can still shift offsets while browsing, because BTCPay has no snapshot/cursor API. Refresh to start a fresh traversal.

## Invoice links and safety

Current BTCPay invoice-state notifications use `type=invoicestate`, `identifier=confirmed` (or another state), a `storeId` and `/invoices/{invoiceId}` links. Older store-scoped and short invoice paths also need translation. Invoice destinations use a store belonging to the authenticated user and its local UUID, never a new raw BTCPay store ID exposure. Missing or inaccessible stores do not receive a guessed invoice destination.

The UI accepts only same-origin app paths or HTTP(S) destinations without embedded credentials. Other schemes, protocol-relative paths and control characters do not become navigation actions. Viewing and marking read use native keyboard-accessible controls. Read status changes only after server confirmation.

Dates on Messages, support wallet connections and admin user creation/last login share `DD.MM.YYYY HH:mm` in the browser's local timezone; invalid or absent values show a fallback.

## Verification

Run the message-feed feature tests and existing wallet security tests against a disposable SQLite target with fake BTCPay calls. Run the messages component tests, frontend lint/typecheck/locale checks, and browser layout checks with synthetic data. Never use the local application's existing merchant database or authenticated browser session for QA.
