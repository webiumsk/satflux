<?php

namespace App\Services\ApiDocumentation;

use RuntimeException;

/** Reviewed response/auth overlays for dynamic controllers; never invokes application endpoints. */
final class DocumentationSchemas
{
    /** @param array<string, mixed> $inferred
     * @return array<string, array<string, mixed>>
     */
    public function documents(array $inferred): array
    {
        $documents = [];
        foreach (['satflux' => 'Satflux API', 'woocommerce' => 'Satflux for WooCommerce', 'webhooks' => 'Inbound webhooks'] as $id => $title) {
            $documents[$id] = [
                'openapi' => '3.1.0',
                'info' => ['title' => $title, 'version' => '1.0.0', 'description' => $this->introduction($id)],
                'servers' => [['url' => '/api', 'description' => 'Same-origin API; interactive requests disabled in the portal.']],
                'paths' => [],
                'components' => ['schemas' => $this->schemas(), 'securitySchemes' => $this->securitySchemes()],
                'x-provenance' => ['generator' => 'dedoc/scramble 0.13.49', 'overlays' => 'app/Services/ApiDocumentation/DocumentationSchemas.php', 'examples' => 'Synthetic only; no runtime response calls.'],
            ];
        }
        foreach (DocumentationRoutes::selected() as $route => $id) {
            [$method, $uri] = explode(' ', $route, 2);
            $path = '/'.substr($uri, 4);
            $operation = $inferred['paths'][$path][strtolower($method)] ?? null;
            if (! is_array($operation)) {
                throw new RuntimeException('Scramble did not infer selected operation '.$route);
            }
            // Only request validation is retained. Dynamic/Eloquent response inference is incomplete
            // without migrations and may serialize hidden fields incorrectly; use reviewed schemas.
            $op = [
                'operationId' => strtolower($method).'_'.preg_replace('/[^a-zA-Z0-9]+/', '_', trim($path, '/')),
                'summary' => $this->summary($method, $path),
                'tags' => [$id],
                'security' => $id === 'satflux' ? [['sessionCookie' => []]] : ($id === 'woocommerce' ? [['integrationToken' => []]] : []),
                'responses' => [],
            ];
            if (isset($operation['requestBody'])) {
                $op['requestBody'] = $operation['requestBody'];
            }
            if (isset($operation['parameters'])) {
                $op['parameters'] = $operation['parameters'];
            }
            $this->overlay($op, $method, $path, $id);
            $documents[$id]['paths'][$path][strtolower($method)] = $op;
        }

        return $documents;
    }

    private function introduction(string $id): string
    {
        return match ($id) {
            'satflux' => 'Initial reviewed scope: account, stores and raffle list/create. Other APIs are explicitly deferred in route-coverage.json. SPA authentication uses the Sanctum session cookie, not a Bearer token. First GET /sanctum/csrf-cookie, then send credentials and the decoded XSRF-TOKEN cookie as X-XSRF-TOKEN on writes; CSRF mismatch returns 419. Browser Origin/Referer must match configured stateful domains. Local store UUIDs identify Satflux routes. Ownership, verified-email, guest and entitlement checks remain server-side. Lists here are unpaginated. Nullable max means unlimited, zero means blocked. Counts can be unavailable; do not use counts as authorization.',
            'woocommerce' => 'The six integration endpoints use a scoped Bearer integration token bound to the current store/company link. OAuth-style connect-code exchange is separate, one-time and valid for ten minutes. Never put production credentials into examples or browsers. Server-side company isolation and issuance entitlements remain authoritative. Monetary unit_price, order_total and total use major units of the declared currency; quantity and tax_rate are numeric (tax percent). Legacy server invoices and encrypted local-first inbox documents have different response shapes. Lists are not paginated here.',
            default => 'Inbound receiver contracts, not outgoing subscription registration. BTCPay signs exact raw bytes using HMAC-SHA256. Bank native requests use a shared header secret; Mailgun signs timestamp plus token and enforces freshness/replay checks. These are distinct authentication protocols. Acknowledgement is not proof of settlement or completed downstream processing. No Bearer authentication is used.',
        };
    }

    private function summary(string $method, string $path): string
    {
        return match ($path) {
            '/user' => 'Read the current account', '/user/limits' => 'Read effective account limits', '/stores' => 'List accessible local stores',
            '/stores/{store}/raffles' => $method === 'POST' ? 'Create a raffle within the store owner quota' : 'List store raffles',
            '/integrations/woocommerce/oauth-exchange' => 'Consume a one-time connect code',
            '/integrations/woocommerce/connection' => 'Inspect the integration and company capabilities',
            '/integrations/woocommerce/contacts/upsert' => 'Upsert a buyer contact',
            '/integrations/woocommerce/documents' => 'Create a server document or local-first inbox entry',
            '/integrations/woocommerce/documents/{documentId}/pdf' => 'Download an auto-issued inbox PDF',
            '/integrations/woocommerce/documents/{documentId}/issue' => 'Issue a document or inbox entry',
            '/integrations/woocommerce/documents/{documentId}' => 'Read a company-scoped document',
            '/webhooks/btcpay' => 'Receive a signed BTCPay delivery', default => 'Receive an authenticated bank email notification',
        };
    }

    /** @param array<string, mixed> $op */
    private function overlay(array &$op, string $method, string $path, string $id): void
    {
        $errors = $id === 'satflux' ? [401, 403, 429] : ($id === 'woocommerce' ? [401, 404, 422, 429] : []);
        foreach ($errors as $status) {
            $op['responses'][(string) $status] = $this->response($this->ref('Error'), match ($status) {
                401 => 'Unauthenticated or invalid credentials', 403 => 'Role, email, ownership, guest or quota denied', 404 => 'Not found in the authorized company/store scope', 422 => 'Validation or business rule failure', default => 'Rate limit exceeded'
            });
        }
        $schema = $this->obj([], [], true);
        $status = '200';
        if ($path === '/user') {
            $schema = $this->ref('Account');
        } elseif ($path === '/user/limits') {
            $schema = $this->ref('AccountLimits');
            $op['parameters'] = [['name' => 'store_id', 'in' => 'query', 'required' => false, 'description' => 'Optional owned local store UUID for event/raffle counts. Upstream count failures currently fall back to zero; never infer authorization from those counts.', 'schema' => ['type' => 'string', 'format' => 'uuid']]];
        } elseif ($path === '/stores') {
            $schema = $this->data(['type' => 'array', 'items' => $this->ref('Store')]);
            $op['description'] = 'Returns an unpaginated data array. Only stores matched in local ownership records and upstream access are included; upstream failure can produce an empty list. The existing Pay Button/API-key formatter includes an upstream btcpay_store_id; route parameters still require local id.';
        } elseif ($id === 'satflux') {
            $schema = $this->data($method === 'GET' ? ['type' => 'array', 'items' => $this->ref('Raffle')] : $this->ref('Raffle'));
            $op['parameters'] = [['name' => 'store', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid'], 'description' => 'Local Satflux store UUID; ownership middleware applies, including existing audited staff exceptions.']];
            foreach ([404, 409, 422, 500, 502, 503] as $code) {
                $op['responses'][(string) $code] = $this->response($this->ref('Error'), $code === 503 ? 'Plugin unavailable, upstream failure or raffle quota verification failed' : 'Upstream or application error; inspect the safe error/code fields');
            }
            if ($method === 'POST') {
                $status = '201';
                $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $this->ref('RaffleCreate'), 'example' => ['name' => 'Synthetic raffle', 'ticketCurrency' => 'SATS', 'ticketPrice' => 100]]]];
                $op['responses']['419'] = $this->response($this->ref('Error'), 'Missing or invalid CSRF token');
            }
        } elseif ($path === '/integrations/woocommerce/oauth-exchange') {
            $op['security'] = [];
            unset($op['responses']['401']);
            $op['parameters'] = [['name' => 'code', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64], 'description' => 'One-time code from the authenticated connect redirect. Intended for one-time consumption; expired or reused codes return 404. Cache-driver get/delete concurrency is not a universal exactly-once guarantee. Sensitive query value; redact logs.']];
            $schema = $this->data($this->ref('ConnectCredentials'));
        } elseif ($path === '/integrations/woocommerce/connection') {
            $schema = $this->data($this->ref('Connection'));
        } elseif (str_ends_with($path, '/contacts/upsert')) {
            $status = '201';
            $schema = $this->data($this->ref('Contact'));
            $op['description'] = 'At least buyer name or email is required by the service. A current company link and invoicing capability are checked server-side.';
        } elseif ($id === 'woocommerce') {
            $schema = $this->data(['anyOf' => [$this->ref('ServerDocument'), $this->ref('PendingInboxDocument'), $this->ref('InboxDocument')]]);
            if ($method === 'POST' && str_ends_with($path, '/documents')) {
                $status = '201';
                $op['requestBody']['content']['application/json']['schema']['properties']['buyer'] = $this->obj([], [], true);
                $op['description'] = 'Returns a server-invoicing document or a local-first inbox entry. Pending inbox entries deduplicate by integration, Woo order ID and document type. This is not a universal exactly-once guarantee; reconcile the returned identifier. Issuance may require an opt-in auto-issue profile and current entitlement.';
            }
            if (str_contains($path, '{documentId}')) {
                $op['parameters'] = [['name' => 'documentId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Document/inbox identifier from the integration response. PDF also accepts its Evolu document UUID. Lookup is scoped to integration, store and current company.']];
            }
            if (str_ends_with($path, '/pdf')) {
                $op['responses'][$status] = ['description' => 'Auto-issued inbox PDF. Requires a stamped number and synced profile; legacy server invoice pdf_url is a separate session-authenticated endpoint.', 'content' => ['application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']]]];

                return;
            }
        } elseif ($path === '/webhooks/btcpay') {
            $op['security'] = [['btcpaySignature' => []]];
            $op['description'] = 'BTCPay-Sig: sha256=<lowercase hex HMAC-SHA256 of the exact unmodified raw body>. Secret resolves from the merchant webhook secret or configured fallback; unknown store identity is rejected even with a valid fallback signature, except the separately configured subscription store which intentionally has no merchant row. Provider storeId is an upstream ID in this inbound payload only. No secret configured, missing signature, invalid signature and an unconfigured store identity all fail closed with 401. Duplicate deliveryId returns status duplicate; new accepted deliveries return received and queue work. Acknowledgement does not establish paid state.';
            $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $this->obj(['storeId' => ['type' => 'string', 'description' => 'Upstream BTCPay store identity, not a local route UUID.'], 'deliveryId' => ['type' => 'string'], 'type' => ['type' => 'string'], 'eventType' => ['type' => 'string'], 'invoiceId' => ['type' => 'string']], [], true), 'example' => ['storeId' => 'synthetic-provider-store', 'deliveryId' => 'synthetic-delivery', 'type' => 'InvoiceSettled']]]];
            $schema = $this->obj(['status' => ['type' => 'string', 'enum' => ['received', 'duplicate']]], ['status']);
            foreach ([400, 401] as $code) {
                $op['responses'][(string) $code] = $this->response($this->ref('Error'), $code === 400 ? 'Invalid JSON payload' : 'Signature, configured secret or store identity invalid');
            }
        } elseif ($path === '/webhooks/bank-inbound.mime') {
            $op['security'] = [(object) []];
            $op['x-body-authentication'] = ['alternative' => 'Mailgun', 'requiredFields' => ['timestamp', 'token', 'signature'], 'algorithm' => 'HMAC-SHA256(timestamp + token)', 'note' => 'Signed Mailgun body fields are required; the empty OpenAPI security object is not unauthenticated acceptance.'];
            $op['description'] = 'Shared Mailgun original-MIME destination for store SEPA notifications and company bank imports, including a single inbound-route configuration. Requires signed timestamp/token delivery authentication and body-mime up to 262144 bytes. Original MIME and optional envelope sender are encrypted before queueing. Server-resolved recipient ownership selects the processing policy. Stores require the existing UTF-8 profile, aligned Tatra DKIM signature, full body coverage, signed sender/recipient/MIME controls and supported plain-text part before strict transaction parsing. Companies use a separate bounded MIME decoder with legacy charset and multipart support, preserving existing company import/matching behavior without granting bank-origin proof or sending a SEPA report. Invalid store proof never falls back to company decoding. Parsed Mailgun body fields and supplied authentication headers do not establish bank origin. HTTP 200 only acknowledges intake, not payment or completed import. Invalid bank proof requires review; temporary process/DNS errors retry through the queue. Configure the one shared Mailgun destination with this .mime suffix only after deploying compatible consumers and workers; no credentials belong in the URL.';
            $mime = $this->obj([
                'recipient' => ['type' => 'string', 'maxLength' => 255],
                'body-mime' => ['type' => 'string', 'maxLength' => 262144, 'description' => 'Original MIME form-field bytes, preserving line endings; maximum is enforced in bytes. Store verification requires UTF-8. Company MIME decoding supports bounded legacy charsets without changing the store policy.'],
                'sender' => ['type' => 'string', 'maxLength' => 255, 'description' => 'Optional Mailgun envelope sender, encrypted before queueing and preferred by legacy company importing. It does not attest bank origin and cannot change store verification policy.'],
                'timestamp' => ['type' => 'string', 'pattern' => '^[0-9]+$'],
                'token' => ['type' => 'string', 'minLength' => 1],
                'signature' => ['type' => 'string', 'description' => 'Mailgun lowercase hex HMAC-SHA256(timestamp + token).'],
            ], ['recipient', 'body-mime', 'timestamp', 'token', 'signature'], true);
            $op['requestBody'] = ['required' => true, 'content' => ['multipart/form-data' => ['schema' => $mime], 'application/x-www-form-urlencoded' => ['schema' => $mime]]];
            $schema = $this->obj(['accepted' => ['type' => 'boolean', 'const' => true]], ['accepted']);
            foreach ([403, 422, 503] as $code) {
                $op['responses'][(string) $code] = $this->response($this->ref('Error'), match ($code) {
                    403 => 'Invalid signature, stale timestamp or replay', 422 => 'Missing or oversized original MIME', default => 'Mailgun signing key not configured'
                });
            }
        } else {
            $op['security'] = [['bankInboundSecret' => []], (object) []];
            $op['x-body-authentication'] = ['alternative' => 'Mailgun', 'requiredFields' => ['timestamp', 'token', 'signature'], 'algorithm' => 'HMAC-SHA256(timestamp + token)', 'note' => 'OpenAPI cannot express body-field security schemes. The empty security alternative means signed Mailgun body, never unauthenticated acceptance.'];
            $op['description'] = 'Two alternatives: native JSON with X-Bank-Inbound-Secret (constant-time shared-secret comparison), or Mailgun form fields. Mailgun mode is selected when recipient, stripped-text or body-plain is filled: signature is lowercase hex HMAC-SHA256(timestamp + token, Mailgun signing key), NOT raw-body HMAC. Timestamp must be numeric and within configured max age (default 300 seconds, absolute clock difference); token replay is rejected using the configured cache. Mailgun signature fields are mandatory for that alternative; no bank header is required. Both paths fail closed when their configured secret is missing. HTTP 200 only means job accepted; it does not establish a payment.';
            $op['description'] .= ' SEPA automatic confirmation additionally requires observed beneficiary/credit evidence and bank-origin verification. Only an authenticated native receiving-MTA adapter may supply bank_authentication from independently verified DKIM/DMARC results; arbitrary MIME authentication headers and Mailgun delivery authentication do not prove bank origin. Incomplete or unverified SEPA notifications remain unconfirmed and require manual bank verification.';
            $native = $this->obj(['to' => ['type' => 'string', 'maxLength' => 255], 'from' => ['type' => 'string', 'maxLength' => 255], 'body' => ['type' => 'string', 'maxLength' => 65535], 'subject' => ['type' => 'string', 'maxLength' => 500], 'headers' => ['type' => 'string', 'maxLength' => 65535]], ['to', 'from', 'body']);
            $native['properties']['bank_authentication'] = $this->obj([
                'dkim_result' => ['type' => 'string', 'description' => 'Verified receiving-MTA result. Only pass can establish origin.'],
                'dkim_domain' => ['type' => 'string', 'description' => 'Verified DKIM signing domain aligned with the bank From domain; only supported bank domains qualify.'],
                'dmarc_result' => ['type' => 'string', 'description' => 'Verified receiving-MTA result. Only pass can establish origin.'],
            ], ['dkim_result', 'dkim_domain', 'dmarc_result']);
            $mailgun = $this->obj([
                'recipient' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                'sender' => ['type' => 'string'],
                'from' => ['type' => 'string'],
                'From' => ['type' => 'string'],
                'stripped-text' => ['type' => 'string'],
                'body-plain' => ['type' => 'string'],
                'stripped-html' => ['type' => 'string'],
                'body-html' => ['type' => 'string'],
                'timestamp' => ['type' => 'string', 'pattern' => '^[0-9]+$'],
                'token' => ['type' => 'string', 'minLength' => 1],
                'signature' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
                'subject' => ['type' => 'string', 'maxLength' => 500],
                'message-headers' => ['type' => 'string'],
            ], ['recipient', 'timestamp', 'token', 'signature'], true);
            $mailgun['allOf'] = [
                ['anyOf' => array_map(fn (string $field): array => ['required' => [$field], 'properties' => [$field => ['type' => 'string', 'minLength' => 1]]], ['sender', 'from', 'From'])],
                ['anyOf' => array_map(fn (string $field): array => ['required' => [$field], 'properties' => [$field => ['type' => 'string', 'minLength' => 1]]], ['stripped-text', 'body-plain', 'stripped-html', 'body-html'])],
            ];
            $mailgun['description'] = 'Recipient is mandatory. Sender fallback precedence is sender, from, From; body fallback precedence is stripped-text, body-plain, stripped-html, body-html. The first truthy value is trimmed. Normalized sender and body must be nonempty and at most 255 and 65535 characters respectively. Signature covers timestamp plus token regardless of selected email aliases.';
            $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $native], 'application/x-www-form-urlencoded' => ['schema' => $mailgun], 'multipart/form-data' => ['schema' => $mailgun]]];
            $schema = $this->obj(['accepted' => ['type' => 'boolean', 'const' => true]], ['accepted']);
            foreach ([403, 422, 503] as $code) {
                $op['responses'][(string) $code] = $this->response($this->ref('Error'), match ($code) {
                    403 => 'Invalid secret/signature, stale timestamp or replay', 422 => 'Invalid normalized notification fields', default => 'Required signing secret not configured'
                });
            }
        }
        $op['responses'][$status] = $this->response($schema, $status === '201' ? 'Created' : 'Success');
    }

    /** @return array<string, mixed> */
    private function securitySchemes(): array
    {
        return [
            'sessionCookie' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'laravel_session', 'description' => 'Configured Laravel session cookie name may differ. Initialize /sanctum/csrf-cookie and send X-XSRF-TOKEN on state-changing SPA requests.'],
            'integrationToken' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Per-integration scoped token; current company link required.'],
            'btcpaySignature' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'BTCPay-Sig', 'description' => 'sha256= plus HMAC-SHA256 of exact raw bytes; never enter the signing secret here.'],
            'bankInboundSecret' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Bank-Inbound-Secret', 'description' => 'Native bank notification shared secret. Mailgun alternative uses signed body fields; see operation.'],
        ];
    }

    /** @return array<string, mixed> */
    private function schemas(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $uuid = ['type' => 'string', 'format' => 'uuid'];
        $limit = $this->obj(['current' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Optional count, not an authorization decision.'], 'max' => ['type' => ['integer', 'null'], 'minimum' => 0, 'description' => 'null means unlimited; zero means the feature is blocked.'], 'unlimited' => ['type' => 'boolean'], 'allowed' => ['type' => 'boolean']], ['max', 'unlimited']);
        $schemas = [
            'Error' => $this->obj(['message' => ['type' => 'string'], 'error' => ['type' => 'string'], 'code' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]]], [], true),
            'Account' => $this->obj(['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'email' => $nullableString, 'role' => ['type' => 'string'], 'is_guest' => ['type' => 'boolean'], 'plan' => ['type' => ['object', 'null'], 'additionalProperties' => true], 'subscription' => ['type' => ['object', 'null'], 'additionalProperties' => true], 'effective_access' => $this->obj([], [], true), 'purchase_availability' => $this->obj([], [], true)], ['id', 'effective_access', 'purchase_availability'], true),
            'AccountLimits' => $this->obj(array_fill_keys(['stores', 'api_keys', 'ln_addresses', 'events', 'raffles', 'companies', 'user_api_keys'], $limit) + ['policy_version' => ['type' => 'string']], ['stores', 'api_keys', 'ln_addresses', 'events', 'raffles', 'companies', 'user_api_keys', 'policy_version']),
            'BoltzServiceAlert' => $this->obj(['active' => ['type' => 'boolean', 'description' => 'This store currently uses the paused Boltz receiving integration.'], 'code' => ['type' => 'string', 'enum' => ['boltz_receiving_unavailable']], 'new_connections_available' => ['type' => 'boolean']], ['active', 'code', 'new_connections_available']),
            'Store' => $this->obj(['id' => $uuid, 'name' => ['type' => 'string'], 'btcpay_store_id' => ['type' => 'string', 'description' => 'Existing deliberate Pay Button/API-key formatting exception; use id for Satflux API routes.'], 'boltz_service_alert' => $this->ref('BoltzServiceAlert')], ['id', 'name'], true),
            'Raffle' => $this->obj(['id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'description' => $nullableString, 'ticketCurrency' => $nullableString, 'ticketPrice' => ['type' => ['number', 'null'], 'description' => 'Major units of ticketCurrency; SATS values are whole satoshis.'], 'ticketPriceSats' => ['type' => ['integer', 'null'], 'minimum' => 1], 'maxTickets' => ['type' => ['integer', 'null'], 'minimum' => 1]], [], true),
            'RaffleCreate' => $this->obj(['name' => ['type' => 'string', 'maxLength' => 255], 'description' => $nullableString + ['maxLength' => 8000], 'ticketCurrency' => $nullableString + ['pattern' => '^[A-Za-z]{3,16}$'], 'ticketPrice' => ['type' => ['number', 'null'], 'exclusiveMinimum' => 0], 'ticketPriceSats' => ['type' => ['integer', 'null'], 'minimum' => 1], 'maxTickets' => ['type' => ['integer', 'null'], 'minimum' => 1]], ['name'], true) + ['anyOf' => [['required' => ['ticketCurrency', 'ticketPrice'], 'properties' => ['ticketCurrency' => ['type' => 'string'], 'ticketPrice' => ['type' => 'number']]], ['required' => ['ticketPriceSats'], 'properties' => ['ticketPriceSats' => ['type' => 'integer']]]], 'description' => 'Supply ticketCurrency and positive ticketPrice, or legacy ticketPriceSats. SATS price must be a whole number; currency pair takes precedence.'],
            'ConnectCredentials' => $this->obj(['btcpay_url' => ['type' => 'string', 'format' => 'uri'], 'api_key' => ['type' => 'string', 'description' => 'Merchant BTCPay API credential returned once to the trusted plugin.'], 'store_id' => ['type' => 'string', 'description' => 'Provider store ID required by the existing WordPress BTCPay integration.'], 'integration_token' => ['type' => 'string', 'description' => 'Sensitive credential returned once to the trusted plugin; never log or expose in examples.'], 'integration_secret' => ['type' => 'string', 'description' => 'Sensitive credential returned once to the trusted plugin; never log or expose in examples.'], 'invoicing_enabled' => ['type' => 'string', 'enum' => ['0', '1']], 'satflux_store_id' => $uuid, 'satflux_company_id' => $uuid], ['btcpay_url', 'api_key', 'store_id', 'integration_token', 'integration_secret', 'invoicing_enabled']),
            'Connection' => $this->obj(['store' => $this->ref('Store'), 'company' => ['type' => ['object', 'null'], 'properties' => ['id' => $uuid, 'name' => ['type' => 'string']]], 'invoicing_enabled' => ['type' => 'boolean'], 'auto_issue_enabled' => ['type' => 'boolean'], 'inbox_mode' => ['type' => 'boolean'], 'local_first' => ['type' => 'boolean'], 'uses_server_invoicing' => ['type' => ['boolean', 'null']], 'integration_inbox_path' => ['type' => 'string'], 'invoices_path' => $nullableString], ['store', 'company', 'invoicing_enabled', 'auto_issue_enabled', 'inbox_mode', 'local_first', 'uses_server_invoicing', 'integration_inbox_path', 'invoices_path']),
            'Contact' => $this->obj(['id' => $uuid, 'company_id' => $uuid, 'name' => ['type' => 'string'], 'email' => $nullableString], ['id', 'company_id'], true),
            'ServerDocument' => $this->obj(['id' => $uuid, 'number' => $nullableString, 'status' => ['type' => 'string'], 'total' => ['type' => ['string', 'number'], 'description' => 'Major currency units; Eloquent decimals serialize as strings.'], 'currency' => ['type' => 'string'], 'payment_token' => $nullableString, 'pdf_url' => $nullableString, 'payment_url' => $nullableString], ['id', 'status', 'currency', 'total', 'number', 'payment_token', 'pdf_url', 'payment_url']),
            'PendingInboxDocument' => $this->obj(['inbox_id' => $uuid, 'evolu_document_id' => $nullableString, 'woocommerce_order_id' => ['type' => ['integer', 'null']], 'status' => ['type' => 'string'], 'created_at' => $nullableString, 'payload' => $this->obj([], [], true), 'number' => $nullableString, 'auto_issued' => ['type' => 'boolean'], 'email_queued' => ['type' => 'boolean'], 'emailed' => ['type' => 'boolean'], 'pdf_available' => ['type' => 'boolean'], 'summary' => $this->obj([], [], true), 'auto_issue_skipped' => $nullableString], ['inbox_id', 'evolu_document_id', 'woocommerce_order_id', 'status', 'payload', 'number', 'auto_issued', 'email_queued', 'emailed', 'pdf_available', 'summary'], true),
            'InboxDocument' => $this->obj(['id' => ['type' => ['string', 'null'], 'description' => 'Pending entry may use inbox ID; issued representation uses nullable Evolu ID.'], 'inbox_id' => $uuid, 'evolu_document_id' => $nullableString, 'number' => $nullableString, 'status' => ['type' => 'string'], 'woocommerce_order_id' => ['type' => ['integer', 'null']], 'currency' => ['type' => 'string'], 'payment_token' => ['type' => 'null'], 'pdf_url' => ['type' => 'null'], 'payment_url' => ['type' => 'null'], 'summary' => $this->obj([], [], true), 'auto_issue_skipped' => $nullableString], ['id', 'status'], true),
        ];

        return $schemas;
    }

    /** @return array<string, mixed> */
    private function ref(string $name): array
    {
        return ['$ref' => '#/components/schemas/'.$name];
    }

    /** @param array<string, mixed> $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function obj(array $properties, array $required = [], bool $additional = false): array
    {
        return ['type' => 'object', 'properties' => $properties === [] ? (object) [] : $properties, 'required' => $required, 'additionalProperties' => $additional];
    }

    /** @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function data(array $schema): array
    {
        return $this->obj(['data' => $schema], ['data']);
    }

    /** @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function response(array $schema, string $description): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
    }
}
