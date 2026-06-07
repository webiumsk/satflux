# Data retention and minimization

How satflux limits stored merchant and customer data, and what operators should configure.

## Roles (GDPR)

- **Satflux** is the controller for platform accounts (registration, billing, API keys).
- **Merchants** are controllers for their customers (`company_contacts`, issued invoices).
- Satflux acts as a **processor** for merchant customer data in the invoicing module.

Customer erasure requests should normally be handled by the merchant; satflux provides anonymization and retention tools.

## What we store

| Data | Location | Minimization |
|------|----------|--------------|
| Merchant company profile | `companies` | Soft delete; hard delete after grace (retention job) |
| Customer contacts | `company_contacts` | Anonymized if issued documents exist; else hard delete |
| Issued invoice buyer | `business_documents.buyer_snapshot` | Frozen at **issue**; survives contact anonymization |
| BTCPay webhooks | `webhook_events.payload` | Purged after processing + retention days |
| Audit trail | `audit_logs` | Email addresses stored as hashes for `email_sent`; rows purged by age |
| Store exports | `storage/app/exports` | File TTL via retention job |
| Public BTC pay link | `payment_token` | Cleared when document marked paid (configurable) |

Registry autocomplete (subjekt.sk / OpenRegistry) is **not persisted** - proxy only.

## Buyer snapshot

On `POST .../documents/{id}/issue`, buyer fields are copied to `buyer_snapshot`. PDF, ISDOC, UBL, and emails use `resolvedBuyer()` (snapshot first, else live contact).

## Contact delete

`DELETE .../contacts/{id}`:

- If the contact has issued/paid/cancelled documents: **anonymize** (PII cleared, row kept).
- Otherwise: **hard delete**.

## Scheduled retention

Command: `php artisan data:retention-run` (scheduled daily 04:00 when enabled).

Requires `DATA_RETENTION_ENABLED=true` in `.env` (or `--force` for manual runs).

| Setting | Default | Action |
|---------|---------|--------|
| `DATA_RETENTION_WEBHOOK_EVENTS_DAYS` | 90 | Delete processed `webhook_events` |
| `DATA_RETENTION_AUDIT_LOGS_DAYS` | 730 | Delete old `audit_logs` |
| `DATA_RETENTION_EXPORT_FILES_DAYS` | 30 | Delete export files + clear paths |
| `DATA_RETENTION_DRAFT_DOCUMENTS_DAYS` | 365 | Delete stale draft invoices |
| `DATA_RETENTION_SOFT_DELETED_COMPANIES_DAYS` | 30 | `forceDelete` companies in trash |
| `DATA_RETENTION_CANCELLED_EXPENSES_DAYS` | 90 | Hard-delete cancelled `business_expenses` and attachment files |
| `DATA_RETENTION_CLEAR_PAYMENT_TOKEN_WHEN_PAID` | true | Revoke `/pay/i/{token}` after paid |

Use `--dry-run` to preview counts.

## Backups and logs

Host backups ([`backup.sh`](../backup.sh)) may retain deleted data until rotation expires. Tune backup retention separately.

Production: use `LOG_LEVEL=warning` or `error` to avoid request PII in application logs.

## Subprocessors

Document in your privacy policy: hosting, BTCPay Server, optional OpenRegistry, merchant-configured SMTP, optional Sentry/Matomo from `.env`.

## Related

- [BUSINESS_INVOICING.md](BUSINESS_INVOICING.md) - subscription churn keeps invoicing data
- `config/data_retention.php` - all retention env keys
