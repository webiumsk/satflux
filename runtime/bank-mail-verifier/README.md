# Bank-mail MIME verifier

Repository-local Node22 runtime used by the Laravel bank-mail worker. It verifies
original Mailgun `body-mime` bytes independently of Mailgun's parsed body or
untrusted authentication-result headers. It does not parse transaction fields or
mark invoices paid. The PHP caller owns delivery authentication, private encrypted
queue persistence, the strict transaction parser, event identity and reporting.

## Runtime contract

Install the nested lock with `npm ci --omit=dev --ignore-scripts` from this directory.
There is no global command, HTTP listener, new MTA or root frontend dependency.
Invoke `node runtime/bank-mail-verifier/cli.mjs` from the repository root and write
one JSON object to stdin: `rawMime` is the original UTF-8 MIME string and `recipient`
is the authenticated Mailgun envelope recipient. Do not place MIME in arguments,
environment variables, logs or exception messages. The parent must enforce its
own 15-second process timeout and bound captured stdout/stderr.

Exactly one JSON object is returned. Verified output has `status: verified`,
`reason: verified`, `bankDomain`, `from`, `subject`, `body`, and `contentSha256`.
The digest is SHA-256 of the exact input UTF-8 MIME bytes, not decoded text. The
caller must check that binding before using the decoded body. Invalid or retryable
output contains only `status` and a stable `reason` code. Invalid means unsupported
or unauthenticated content requiring review. Retryable means verification or DNS
was temporarily unavailable; preserve queue retry rather than confirming payment.
Only verified output contains decoded message data.

Initial profile and coverage constraints:

- Tatra banka domain or a subdomain, a unique From mailbox and unique matching
  To mailbox. Both are signed. The recipient must equal the envelope recipient.
- Actual per-signature aligned DKIM pass using mailauth strict mode. RSA-SHA256
  keys must meet its explicit 1024-bit RFC minimum; Ed25519-SHA256 is also allowed.
  Expired/future constraints fail; missing optional `t=` is not rejected by itself.
- No body-length-limited `l=` signatures. Full canonical body length must match
  the length hashed. Present Content-Type, Content-Transfer-Encoding and
  MIME-Version controls must be signed. Subject is signed as an initial profile
  constraint, not a claim that it independently proves a credit.
- One explicit `text/plain` part, UTF-8 or US-ASCII, no attachments, HTML,
  multipart alternatives or unqualified charsets. CRLF wire syntax, bounded
  headers and no duplicate identity/MIME singleton headers. This is deliberately
  a supported subset, not a claim that every current Tatra email has this form.
- Raw MIME at most 256 KiB; decoded text at most 65,535 UTF-8 bytes; subject at
  most 500 JavaScript characters and From at most 255 characters.
- At most five signatures and five DNS TXT lookups. DNS names are limited to
  valid DKIM selectors beneath the Tatra domain; no HTTP, SPF/Received inference,
  ARC/BIMI or arbitrary message-supplied network destination is used. Each lookup
  has a two-second deadline, the total DNS budget is ten seconds and the CLI
  exits with a typed timeout after thirteen seconds.

Multiple bounded signatures are allowed when an eligible bank proof passes.
Forged Authentication-Results and X-Mailgun Pass headers never establish trust.
DNS absence is invalid; temporary DNS failure is retryable. No debug/raw error is
returned. The production CLI accepts no resolver, key, test flag or configuration
from JSON, argv or environment. Process-owner preload capabilities are outside
that input contract and must remain controlled by the deployment environment.

## Dependencies and tests

mailauth8.0.4 and mailparser3.14.1 are exact runtime pins. The nested override pins
joi18.3.1 because mailauth's original18.3.0 has
[GHSA-4528-x5fx-h6hg](https://github.com/advisories/GHSA-4528-x5fx-h6hg).
Do not use an automatic major downgrade from `npm audit fix --force`.

`npm test` executes Node's test runner with real synthetic DKIM signatures and
fake DNS. Keys are generated in memory, never committed, printed or written to
disk. Tests cover actual CLI execution, tampering, forged result headers,
recipient/domain binding, duplicate and unsigned MIME controls, unsupported MIME,
partial signatures, time constraints, size/signature/DNS limits and retry paths.
Process-spawning tests require a runner that permits subprocess creation; sandbox
EPERM is not a test pass or a reason to skip those assertions.

The exported `verify(input, {resolver})` function permits a library-only resolver
injection for tests. Production CLI never exposes it. Cross-language tests can
use `test/build-message.mjs`: stdin `{recipient, body}` yields synthetic signed
MIME plus a public DNS record; no private key is returned. A temporary test-only
Node launcher may preload `test/preload-dns.mjs` with `--import` and set
`SATFLUX_TEST_DNS_FIXTURE` to the resulting public-record JSON file. That preload
is not imported by production code. Its output is not live-bank qualification.

No current original Tatra message was available when this profile was built.
A real message is still needed for private operational interoperability checks;
synthetic cryptographic tests do not prove actual bank signing/layout coverage.
