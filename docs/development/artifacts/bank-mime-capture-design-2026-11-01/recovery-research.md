# Tatra bank notification recovery research

Date checked: 2026-11-01. Researcher: Codex researcher specialist.

## Scope and evidence boundary

Repository: `webiumsk/satflux`; observed checkout `fix/sepa-confirmation-isolation` at `885168f2b3ebaaf70b6724eebe541084c969aa00`, with pre-existing dirty work preserved. This is not an assertion about the running server or PR414 deployment. Read repository instructions, researcher role, security guidance and payments-and-entitlements skill. Repository context: `docs/development/PROJECT_STATE.md`, opening section “Bank runtime narrowed to MIME rejection - 2026-11-01”. No application code was explored; parent reports the Graphify query was stale/no MIME edges. No code/runtime/version compatibility claim was needed.

Accepted assignment: official Mailgun/Tatra primary-source research only; no production API calls, account access, credentials, messages, repository edits, configuration changes, payments or publication. Sole written output is this assigned `/tmp` report.

Reported incident inputs, not independently reproduced: Free plan; one shared EU route forwarding to `.mime`; HTTP delivery successful; application rejection `invalid_signature`; no original EML; historical storage and canonical EU API retrieval both returned 404; MIME/Quick View disabled in UI. Neither the reason for 404 nor the bank cryptographic failure is established by those facts.

## Conclusions

1. **No verified Free-plan retention bypass.** Official material does not establish that inbound route `store()` operates independently of plan message retention. A Free account's one route can structurally contain more than one action, but that is not proof that `store()` is available/useful on this particular Free account.
2. **Existing original recovery is currently blocked.** There is no documented alternative endpoint that reconstructs an expired/unavailable original from event logs. Do not repeat the same failed download or promise support recovery.
3. **A header-only diagnostic may still be available, not full signature verification.** Existing raw event JSON can be inspected privately for additional authentication metadata, but official docs do not promise that events include complete MIME headers or DKIM/SPF results. The inbound HTTP request carries richer `message-headers`, including on `.mime` destinations. If that payload was not retained, the event is not a substitute.
4. **Next normal mail can be captured without relying on Mailgun retention.** The current `.mime` HTTP delivery already contains the raw message. Proposed engineering path: a separately authorized, reviewed private capture at the existing owned receiver before it discards the message. This is an inference from the payload contract, not an implemented or tested capture mechanism. Await an ordinary notification; no new transfer is needed. This does not recover the old notification or itself authorize settlement.

## Verified official sources

All URLs were read through public browsing on 2026-11-01. Website content has no immutable source revision; relative “last updated” labels are not a guarantee of entitlement accuracy.

### S1 - Route action contract

https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/route-actions

The manual describes `forward()`, `store()` and `stop()`. It describes temporary inbound storage for up to three days; omitting `notify` still permits later Messages API retrieval. It does not identify Free-plan availability or an exemption from domain retention.

It also warns that forwarding to another email address can invalidate DKIM if click/open/unsubscribe tracking modifies the message. Therefore an arbitrary forwarded mailbox copy is not a guaranteed byte-preserving capture path.

### S2 - Current support article qualifies route storage by plan

https://help.mailgun.com/hc/en-us/articles/360011355893-Routes

The overview and “Store and notify” description both give up to seven days **depending on account plan and domain settings**. This prevents interpreting S1's three-day description as a universal Free guarantee. The difference is documentation ambiguity, not evidence that route storage bypasses message retention.

### S3 - Current pricing

https://www.mailgun.com/pricing/

Free lists one inbound route, one day of log retention and ticket support. It does not list message retention. Foundation explicitly lists one day of message retention and Scale up to seven days. This omission alone is not proof that a legacy/current Free account cannot execute `store()`; account-specific confirmation is required. No paid upgrade is recommended.

### S4 - Multiple actions in one route

https://documentation.mailgun.com/docs/mailgun/api-reference/send/mailgun/routes

The Routes API defines `action` as an array and permits multiple action parameters. It therefore does not require a second route merely to add another action.

https://www.mailgun.com/blog/product/store-a-temporary-mailbox-for-all-your-incoming-email/

This official article, dated July 16, 2019, expressly describes combining store with forward or stop. Its legacy API examples and three-day storage description do not establish current Free entitlements. Combined with S3, one-route structural compatibility is verified; no-cost storage availability remains unverified.

### S5 - Inbound HTTP payload, including raw MIME

https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/receive-http

The base payload includes ordered MIME `message-headers`. A destination ending in `mime` or `raw-mime` receives `body-mime` rather than parsed plain/HTML bodies, while the other base fields remain available. Thus raw delivery to the owned application does not require a stored-message retrieval entitlement. HTTP 200 ends automatic retries; it does not mean the application's proof verifier accepted the bank message. This contract supports future private capture at the receiver, subject to an implementation review.

### S6 - Stored-message recovery has a retention dependency

https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/view-stored-messages

Storage URLs can be located in expanded accepted/delivered/failed log entries or the Logs API. The example retrieves `body-mime` with `Accept: message/rfc2822`.

https://documentation.mailgun.com/docs/mailgun/api-reference/send/mailgun/messages/get-v3-domains--domain-name--messages--storage-key-

The API explicitly limits storage keys to the domain's message-retention policy. Its published responses include 404. It does not give a recovery procedure or one-to-one cause for a 404. Presence of a storage URL/key in an event does not establish present availability of its body.

### S7 - Logs and headers are different evidence

https://documentation.mailgun.com/docs/mailgun/user-manual/webhooks/webhook-payloads

Official accepted/delivered event examples show a small `message.headers` object containing from/to/subject/message-id. They explicitly say examples are not exhaustive. There is no guarantee there of full MIME `message-headers`, `Authentication-Results`, or inbound DKIM verdicts. `flags.is-authenticated` and `primary-dkim` in a sending/delivery example must not be reinterpreted as proof that the received bank's signature passed.

https://documentation.mailgun.com/docs/mailgun/user-manual/receive-forward-store/spam-filter

If the domain is configured to mark spam with MIME headers, Mailgun documents `X-Mailgun-Dkim-Check-Result` and `X-Mailgun-Spf`. Their presence can provide corroborating diagnostic evidence. This documentation does not say those headers are always generated or exposed in event logs. Do not enable/change the spam filter merely for this research, and do not replace local bank proof with a reported header verdict.

### S8 - Bank resend/export boundary

https://www.tatrabanka.sk/sk/personal/ucet-platby/internet-banking/b-mail/

The bank documents B-mail setup/change, notification channels and account/card notifications. It calls B-mail informational. I found no documented self-service resend or original EML export for an already sent transaction notification on this page or the Internet banking FAQ.

https://www.tatrabanka.sk/sk/pomoc-podpora/casto-kladene-otazky/internet-banking/

The FAQ documents exports of movements as PDF/XLS/TXT/XML/CSV and accounting statements as camt.053 XML, delivered through its message box. These are transaction/account exports, not a reproduction of the original signed bank notification. They cannot diagnose the original email signature or be silently substituted into the current email-proof policy.

## Minimal next operator step

**Read only:** In the existing EU event, open the expanded/raw event details and inspect privately for actual authentication/header fields. Record only whether `Authentication-Results`, `DKIM-Signature`, `X-Mailgun-Dkim-Check-Result`, or `X-Mailgun-Spf` exists and their relevant verdict/domain/algorithm metadata, with addresses, subjects, account identifiers, storage keys, tokens and signatures excluded from shared output. If the UI only exposes summary fields, record that limitation and stop; no MIME availability or bank validation conclusion follows.

**Original recovery:** The owner can ask Mailgun through their authenticated support ticket channel whether the *existing* EU message is still recoverable and whether this exact Free account's existing route can use `store()` plus forward, at no plan change, with what retention. This is a question, not a claimed recovery path; no ticket was sent. Provide message identifiers only privately to the provider. If unavailable, ask Tatra through its established authenticated support channel whether it can reissue/export that notification as original signed email without a new transaction. No public bank source confirms it can.

**Future capture:** If provider recovery/resend is unavailable, the investigation remains blocked on original bytes until an approved private capture exists at the current receiver and the next normal bank email arrives. A narrow private capture must preserve the existing verification/settlement path, avoid request-body logging/public collectors, retain only an explicitly bounded encrypted original, and restrict owner access/retention. This report does not authorize that implementation or change production configuration. Merely adding `store()` without account confirmation would leave the Free-plan uncertainty unresolved.

## Exact checks and outstanding blockers

- Completed: public official documentation searches and page reads listed above; checkout/branch/revision/status inspection; relevant instruction/skill reads.
- No application tests, production network/API probes, credential access, message downloads or cryptographic verification were run. No installed packages/tooling changed.
- No repository/shared-state/Git/publication writes; only this assigned temporary report.
- Blockers: absent original MIME; unknown Free account/domain storage entitlement; no documented bank original-email resend/export; no verified private next-message capture. Cryptographic failure versus stricter verifier policy remains unresolved by documentation alone.
