# API reference

The API portal is available at `/admin/api-docs` for support and administrator
accounts through the existing authentication and email-verification middleware.
Open **Admin -> API reference** after signing in. The catalog and every document
also enforce authorization on the server. Existing public `/documentation` user
manuals remain separate.

## Initial coverage

Choose one of three documents:

- **Satflux**: representative account, account limits, store listing and raffle
  listing/creation contracts.
- **Satflux for WooCommerce**: single-use connect-code exchange and all six
  integration-token endpoints, including the PDF download.
- **Inbound webhooks**: BTCPay and bank inbound delivery, including their distinct
  authentication formats.

This first phase covers selected contracts. The versioned `docs/api` route
inventory identifies documented operations and explicitly deferred routes. CI
checks the inventory against registered `api/*` routes so additions, removals
and middleware changes require review. Automatically added HEAD methods and other
browser/framework/vendor routes are outside this inventory. Coverage will expand
in later changes.

Provider links lead to the independently maintained Chorala specification, BTCPay
Greenfield reference and WooCommerce core REST reference. Those contracts have
their own version histories, permissions and deployment status. The portal does
not fetch provider documents or send Satflux cookies to their services. BTCPay
plugin-specific contracts still require the matching plugin release documentation.

## Authentication and identifiers

Satflux's browser API uses a Sanctum session cookie. Initialize CSRF with
`GET /sanctum/csrf-cookie`; modifying browser requests send `X-XSRF-TOKEN` with the
decoded XSRF cookie value. Session authentication is distinct from the scoped
WooCommerce integration bearer token. The connect exchange uses its expiring,
single-use code before that bearer token exists.

BTCPay webhooks verify HMAC-SHA256 over the exact raw request body. Bank inbound
uses a native shared-secret header or Mailgun's timestamp/token signature. Follow
each operation's contract; a generic bearer credential does not authenticate
these webhooks. See [WooCommerce](WOOCOMMERCE.md) for the outgoing invoice-paid
webhook, which belongs to the separate plugin receiver contract.

Store parameters in Satflux browser operations use local UUIDs. Upstream provider
IDs and their permissions belong to their respective contracts. Documentation
visibility never grants store/company access or changes plan eligibility.

## Presentation and generation

Scalar is pinned and bundled in the application, with interactive test requests,
external proxies, telemetry, default external fonts, AI/MCP document uploads and
credential persistence disabled. Documents load from fixed same-origin authenticated endpoints; external
references are rejected. Examples are synthetic. Do not paste real tokens,
wallet data or merchant documents into examples.

The portal serves reviewed static OpenAPI 3.1 artifacts from `docs/api`. Scramble
is a development dependency used during generation, not by production requests.
Generation must run in a disposable environment with no production `.env`, cached
configuration or external transports. It does not invoke controller endpoints to
capture responses. Inferred contracts receive explicit corrections where dynamic
responses cannot be derived reliably.

The exporter, route policy and schema-validation commands are described alongside
the artifacts in `docs/api`. CI checks deterministic regeneration and validates
each OpenAPI document. Update the source contract and regenerate artifacts together
when changing covered operations. Review deferred inventory entries when adding
new routes, and expand detailed coverage deliberately.

No database migration, provider release or new production secret is needed for
the portal. Deploy the reviewed application revision, its lockfiles and matching
built assets through the normal approval process.
