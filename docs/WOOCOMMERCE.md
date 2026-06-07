# WooCommerce integration

SATFLUX WooCommerce plugin connects WordPress shops to [satflux.io](https://satflux.io) for BTCPay payments, optional business invoicing, and optional Satoshi Tickets.

## Architecture

```
WooCommerce (SATFLUX plugin)
  ├─ Connect (session) → GET /woocommerce/connect
  ├─ Payment → BTCPay Greenfield API (store API key)
  ├─ Invoicing (Pro+) → /api/integrations/woocommerce/*
  └─ Tickets (optional) → BTCPay SatoshiTickets API
```

## Connect flow

1. Merchant opens **WooCommerce → Satflux → Connection** and clicks **Connect to Satflux.io**.
2. Browser redirects to `GET /woocommerce/connect?return_url=...` (HTTPS required, user must be logged in).
3. Satflux redirects back with:
   - `btcpay_url`, `api_key`, `store_id` (BTCPay GUID)
   - `satflux_store_id`, `satflux_company_id` (when linked)
   - `integration_token`, `integration_secret` (for invoicing API + webhooks)
   - `invoicing_enabled=0|1`

Legacy URL `/woocommerce/satoshi-tickets/connect` remains supported.

## Integration API (invoicing)

Auth: `Authorization: Bearer {integration_token}`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/integrations/woocommerce/connection` | Test + store/company info |
| POST | `/api/integrations/woocommerce/contacts/upsert` | Upsert buyer contact |
| POST | `/api/integrations/woocommerce/documents` | Create draft from WC order |
| POST | `/api/integrations/woocommerce/documents/{id}/issue` | Issue document |
| GET | `/api/integrations/woocommerce/documents/{id}` | Status, PDF URL, pay link |

Rate limit: 60 requests/minute per IP.

## Webhooks to WooCommerce

When a business document linked to a WooCommerce order is marked paid, Satflux POSTs to:

`{site}/wp-json/satflux/v1/invoicing-webhook`

Header: `X-Satflux-Signature: HMAC-SHA256(body, integration_secret)`

Payload: `{ "event": "document.paid", "document_id", "woocommerce_order_id", ... }`

BTCPay payment webhooks for shop orders use:

`{site}/wp-json/satflux/v1/webhook` (event `InvoiceSettled`)

## Data model

- `store_integrations` - per-store WooCommerce token (hashed), encrypted secret, webhook URL
- Business documents from WooCommerce store `internal_note`: `woocommerce_order_id={id}`

## Plan requirements

- **Payment only:** any Satflux store with BTCPay credentials
- **Invoicing:** Pro/Enterprise `business_invoicing` + company linked to store (`stores.company_id`)
- **Tickets:** BTCPay SatoshiTickets plugin on the merchant's BTCPay Server

## Upgrade from v1.x (BTCPay Satoshi Tickets plugin)

Plugin v2.0 migrates `btcpay_satoshi_*` options to `satflux_*`, keeps ticket gateway ID for existing orders, and auto-enables Tickets module when ticket products exist.
