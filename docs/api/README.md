# Reviewed API contracts

The protected `/admin/api-docs` portal serves the three committed OpenAPI 3.1
files through authenticated admin/support endpoints. `route-coverage.json` is a
review inventory, not a served document. Public `/documentation` remains user help.

## Build and verification

Install development dependencies, then use an uncached, isolated Laravel testing
environment with SQLite `:memory:`, array cache/session/mail and mocked transports:

```sh
php artisan api-docs:export
php artisan api-docs:export --check
node scripts/api-docs/validate.mjs
```

The export command rejects other configurations and prevents stray Laravel HTTP
requests. CI sets a synthetic localhost BTCPay target and credentials. Do not run
export against deployed configuration. No response calls, merchant credentials or production payloads are needed.
The command migrates only its strictly checked in-memory testing database before
model introspection; the database disappears when the process exits. The generator parses controllers;
it does not execute their HTTP actions. Network-disabled containers provide an
additional boundary during local generation.

Scramble is pinned to 0.13.49 as a development dependency. Composer resolved one
new package without changing existing locked packages on PHP 8.3.31 / Laravel 12.
An initial inference trial reported twelve missing-table diagnostics before the
in-memory migration was added. The final exporter migrates that disposable schema
and rejects any remaining Scramble diagnostic. Eloquent serialization and dynamic
service/proxy results still require explicit reviewed response overlays. Request validation inference is kept
where useful; buyer arrays are corrected to objects and conditional raffle pricing
is specified explicitly. Runtime generation routes from Scramble are disabled,
including in local environments. Production needs only the reviewed JSON files.

## Scope and provenance

Fourteen operations are covered: five representative session endpoints (account,
limits, stores, raffle list/create), six WooCommerce bearer endpoints, one one-time
connect-code exchange, and two inbound webhook receivers. The route inventory
includes runtime `api/*` routes, omits automatically added HEAD methods, and assigns
every operation a document or a deferred-scope reason. Framework/vendor and other
web routes are outside this inventory. It is not a claim that all product APIs
have complete schemas.

`DocumentationRoutes.php` is the allowlist. `DocumentationSchemas.php` contains
reviewed auth/response overlays and synthetic examples. The inventory records route
middleware/actions and SHA-256 hashes for selected controllers and supporting
contract services. Changes make CI stale until a reviewer checks the contract and
regenerates it. Regeneration alone is not a semantic review. Add new operations
with request, response, failure and security review before moving them into scope.

The exact auth protocols differ: Sanctum cookie/CSRF, scoped integration bearer,
one-time code, BTCPay raw-body HMAC, native bank shared header, and Mailgun signed
body fields. OpenAPI cannot express body-field security; the Mailgun alternative
uses an empty security requirement accompanied by `x-body-authentication` and an
explicit mandatory-signature description. It never means the receiver accepts an
unsigned request. Acknowledged webhooks do not imply payment/settlement completion.

Dynamic response schemas intentionally permit provider extensions and incomplete
account policy projections. Money units and nullability are explicit where stable;
these documents are not yet a generated SDK compatibility promise. No pagination
is claimed for the covered lists. Staff must continue checking the deployed provider
and plugin versions through the independent Chorala, BTCPay and WooCommerce links.
