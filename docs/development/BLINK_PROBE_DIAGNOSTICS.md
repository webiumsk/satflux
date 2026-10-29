# Lightning address probe diagnostics

The owner reports Blink verification failing on the live installation while a
local connection succeeds. The exact live failing endpoint is not established.
This diagnostic change preserves capability results, DNS pinning, redirect refusal,
timeouts, caching and wallet-change protections. No plugin or schema change is needed.

## Reading a failure

After deploying this revision, retry the connection once and search the application
log for `LUD-21 probe failed`. A hypothetical example is:

```json
{"stage":"verify","url_host":"lnurl.blink.sv","category":"http_error","http_status":503}
```

This is illustrative, not an observed production response.

| Field | Meaning |
| --- | --- |
| `stage` | `descriptor`: Lightning address metadata; `invoice`: LNURL-pay callback; `verify`: the issued invoice's verification endpoint. |
| `url_host` | Destination hostname only, without path or query. |
| `category` | `dns_rejected`, `request_failed`, `http_error` or `invalid_json`. |
| `http_status` | Included only when an HTTP response exists. |

DNS rejection covers unavailable resolution and non-public addresses. Request
failure omits raw exception text because it can contain private URLs. HTTP errors
include refused redirects. Invalid JSON includes malformed JSON, null and scalars.
No wallet address, URL path/query, invoice, response body, preimage or exception
object is added to these logs. Success remains silent; saves still recheck freshly.

`unreachable` remains the API result for failed descriptor or verify fetches;
failed invoice callbacks retain `invalid_lnurlp`. Do not bypass verification or
change wallets to diagnose connectivity. Rollback requires reverting only the
diagnostic commit; there is no data migration or plugin dependency.

## Validation and limits

Independent source security and QA accepted the reviewed service and diagnostic
tests. Offline disposable PHP 8.3 validation passes 66 tests/278 assertions across
probe diagnostics, receipts, capability and wallet compatibility. Changed-file
Pint and level-5 scoped PHPStan pass. Full local PHPStan is blocked by an unrelated
missing installed Scramble class in the documentation exporter; no baseline or
dependencies were changed. Remote CI must be attributed separately to its revision.

This publication excludes the separate SEPA work, Vite development-only changes,
local filesystem permissions and local database operations. The owner performs
live deployment; publication alone does not verify the production failure cause.
