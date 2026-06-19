<?php

namespace App\Support;

/**
 * Registry of platform-wide runtime settings (migrated from .env.standalone).
 */
final class PlatformSettingsSchema
{
    public const GROUP_AUTH = 'auth';

    public const GROUP_BTCPAY = 'btcpay';

    public const GROUP_INVOICING = 'invoicing';

    public const GROUP_GUEST = 'guest';

    public const GROUP_COMPLIANCE = 'compliance';

    public const GROUP_RETENTION = 'retention';

    public const GROUP_BANK = 'bank';

    public const GROUP_INTEGRATIONS = 'integrations';

    public const GROUP_EXPORT = 'export';

    /**
     * @return list<array{
     *     key: string,
     *     env: string|null,
     *     group: string,
     *     type: string,
     *     secret?: bool
     * }>
     */
    public static function fields(): array
    {
        return [
            // Auth & registration
            ['key' => 'services.lnurl_auth.enabled', 'env' => 'LNURL_AUTH_ENABLED', 'group' => self::GROUP_AUTH, 'type' => 'bool'],
            ['key' => 'services.lnurl_auth.domain', 'env' => 'LNURL_AUTH_DOMAIN', 'group' => self::GROUP_AUTH, 'type' => 'string'],
            ['key' => 'services.nostr_auth.enabled', 'env' => 'NOSTR_AUTH_ENABLED', 'group' => self::GROUP_AUTH, 'type' => 'bool'],
            ['key' => 'services.nostr_auth.challenge_ttl_seconds', 'env' => 'NOSTR_AUTH_CHALLENGE_TTL', 'group' => self::GROUP_AUTH, 'type' => 'int'],
            ['key' => 'guest.seed_first_registration', 'env' => 'SEED_FIRST_REGISTRATION', 'group' => self::GROUP_AUTH, 'type' => 'bool'],
            ['key' => 'services.auth.guest_email_domain', 'env' => 'GUEST_EMAIL_DOMAIN', 'group' => self::GROUP_AUTH, 'type' => 'string'],

            // BTCPay public / checkout
            ['key' => 'services.btcpay.public_url', 'env' => 'BTCPAY_PUBLIC_URL', 'group' => self::GROUP_BTCPAY, 'type' => 'url'],
            ['key' => 'services.btcpay.lightning_address_domain', 'env' => 'BTCPAY_LIGHTNING_ADDRESS_DOMAIN', 'group' => self::GROUP_BTCPAY, 'type' => 'string'],
            ['key' => 'services.btcpay.landing_pay_demo_store_id', 'env' => 'LANDING_PAY_DEMO_STORE_ID', 'group' => self::GROUP_BTCPAY, 'type' => 'uuid'],
            ['key' => 'services.btcpay.subscription_success_url', 'env' => 'SUBSCRIPTION_SUCCESS_URL', 'group' => self::GROUP_BTCPAY, 'type' => 'url'],
            ['key' => 'services.btcpay.subscription_cancel_url', 'env' => 'SUBSCRIPTION_CANCEL_URL', 'group' => self::GROUP_BTCPAY, 'type' => 'url'],
            ['key' => 'services.btcpay.allow_guest_subscriptions', 'env' => 'ALLOW_GUEST_SUBSCRIPTIONS', 'group' => self::GROUP_BTCPAY, 'type' => 'bool'],
            ['key' => 'services.btcpay.subscription_store_id', 'env' => 'SUBSCRIPTION_STORE_ID', 'group' => self::GROUP_BTCPAY, 'type' => 'string'],
            ['key' => 'services.btcpay.subscription_offering_id', 'env' => 'SUBSCRIPTION_OFFERING_ID', 'group' => self::GROUP_BTCPAY, 'type' => 'string'],
            ['key' => 'services.btcpay.subscription_plans.pro', 'env' => 'SUBSCRIPTION_PLAN_PRO_ID', 'group' => self::GROUP_BTCPAY, 'type' => 'string'],
            ['key' => 'services.btcpay.subscription_plans.enterprise', 'env' => 'SUBSCRIPTION_PLAN_ENTERPRISE_ID', 'group' => self::GROUP_BTCPAY, 'type' => 'string'],
            ['key' => 'services.btcpay.subscription_payment_reminder_days', 'env' => 'SUBSCRIPTION_PAYMENT_REMINDER_DAYS', 'group' => self::GROUP_BTCPAY, 'type' => 'int'],

            // Invoicing & e-faktura
            ['key' => 'invoicing.local_first', 'env' => 'INVOICING_LOCAL_FIRST', 'group' => self::GROUP_INVOICING, 'type' => 'bool'],
            ['key' => 'invoicing.woocommerce_inbox_mode', 'env' => 'WOOCOMMERCE_INBOX_MODE', 'group' => self::GROUP_INVOICING, 'type' => 'bool'],
            ['key' => 'invoicing.beta_pro_max_companies', 'env' => 'INVOICING_BETA_PRO_MAX_COMPANIES', 'group' => self::GROUP_INVOICING, 'type' => 'nullable_int'],
            ['key' => 'invoicing.expense_isdoc_extract_free_limit', 'env' => 'INVOICING_EXPENSE_ISDOC_FREE_LIMIT', 'group' => self::GROUP_INVOICING, 'type' => 'int'],
            ['key' => 'invoicing.subscription_billing.company_id', 'env' => 'SUBSCRIPTION_BILLING_COMPANY_ID', 'group' => self::GROUP_INVOICING, 'type' => 'uuid'],
            ['key' => 'efaktura.enabled', 'env' => 'EFAKTURA_ENABLED', 'group' => self::GROUP_INVOICING, 'type' => 'bool'],
            ['key' => 'efaktura.default_provider', 'env' => 'EFAKTURA_PROVIDER', 'group' => self::GROUP_INVOICING, 'type' => 'string'],
            ['key' => 'efaktura.inbound_poll_limit', 'env' => 'EFAKTURA_INBOUND_POLL_LIMIT', 'group' => self::GROUP_INVOICING, 'type' => 'int'],
            ['key' => 'efaktura.allowed_sapi_hosts', 'env' => 'EFAKTURA_SAPI_ALLOWED_HOSTS', 'group' => self::GROUP_INVOICING, 'type' => 'csv_array'],
            ['key' => 'efaktura.providers.sapi_sk.base_url', 'env' => 'EFAKTURA_SAPI_BASE_URL', 'group' => self::GROUP_INVOICING, 'type' => 'url'],
            ['key' => 'efaktura.providers.sapi_sk.send_detail_path', 'env' => 'EFAKTURA_SAPI_SEND_DETAIL_PATH', 'group' => self::GROUP_INVOICING, 'type' => 'string'],

            // Guest lifecycle
            ['key' => 'guest.purge_enabled', 'env' => 'GUEST_PURGE_ENABLED', 'group' => self::GROUP_GUEST, 'type' => 'bool'],
            ['key' => 'guest.idle_days', 'env' => 'GUEST_PURGE_IDLE_DAYS', 'group' => self::GROUP_GUEST, 'type' => 'int'],
            ['key' => 'guest.batch_size', 'env' => 'GUEST_PURGE_BATCH_SIZE', 'group' => self::GROUP_GUEST, 'type' => 'int'],
            ['key' => 'guest.max_stores_check', 'env' => 'GUEST_PURGE_MAX_STORES_CHECK', 'group' => self::GROUP_GUEST, 'type' => 'int'],

            // Compliance
            ['key' => 'compliance.enabled', 'env' => 'COMPLIANCE_SCREENING_ENABLED', 'group' => self::GROUP_COMPLIANCE, 'type' => 'bool'],
            ['key' => 'compliance.geo_block_enabled', 'env' => 'COMPLIANCE_GEO_BLOCK_ENABLED', 'group' => self::GROUP_COMPLIANCE, 'type' => 'bool'],
            ['key' => 'compliance.list_screening_enabled', 'env' => 'COMPLIANCE_LIST_SCREENING_ENABLED', 'group' => self::GROUP_COMPLIANCE, 'type' => 'bool'],
            ['key' => 'compliance.fail_closed', 'env' => 'COMPLIANCE_FAIL_CLOSED', 'group' => self::GROUP_COMPLIANCE, 'type' => 'bool'],
            ['key' => 'compliance.geo_country_override', 'env' => 'COMPLIANCE_GEO_COUNTRY_OVERRIDE', 'group' => self::GROUP_COMPLIANCE, 'type' => 'nullable_string'],
            ['key' => 'compliance.retention_years', 'env' => 'COMPLIANCE_RETENTION_YEARS', 'group' => self::GROUP_COMPLIANCE, 'type' => 'int'],

            // Data retention
            ['key' => 'data_retention.enabled', 'env' => 'DATA_RETENTION_ENABLED', 'group' => self::GROUP_RETENTION, 'type' => 'bool'],
            ['key' => 'data_retention.batch_size', 'env' => 'DATA_RETENTION_BATCH_SIZE', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.webhook_events_days', 'env' => 'DATA_RETENTION_WEBHOOK_EVENTS_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.audit_logs_days', 'env' => 'DATA_RETENTION_AUDIT_LOGS_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.export_files_days', 'env' => 'DATA_RETENTION_EXPORT_FILES_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.draft_documents_days', 'env' => 'DATA_RETENTION_DRAFT_DOCUMENTS_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.soft_deleted_companies_days', 'env' => 'DATA_RETENTION_SOFT_DELETED_COMPANIES_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.cancelled_expenses_days', 'env' => 'DATA_RETENTION_CANCELLED_EXPENSES_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.integration_inbox_closed_days', 'env' => 'DATA_RETENTION_INTEGRATION_INBOX_CLOSED_DAYS', 'group' => self::GROUP_RETENTION, 'type' => 'int'],
            ['key' => 'data_retention.clear_payment_token_when_paid', 'env' => 'DATA_RETENTION_CLEAR_PAYMENT_TOKEN_WHEN_PAID', 'group' => self::GROUP_RETENTION, 'type' => 'bool'],

            // Bank inbound
            ['key' => 'bank_inbound.enabled', 'env' => 'BANK_INBOUND_ENABLED', 'group' => self::GROUP_BANK, 'type' => 'bool'],
            ['key' => 'bank_inbound.domain', 'env' => 'BANK_INBOUND_DOMAIN', 'group' => self::GROUP_BANK, 'type' => 'string'],
            ['key' => 'bank_inbound.address_prefix', 'env' => 'BANK_INBOUND_ADDRESS_PREFIX', 'group' => self::GROUP_BANK, 'type' => 'string'],
            ['key' => 'bank_inbound.max_address_length', 'env' => 'BANK_INBOUND_MAX_ADDRESS_LENGTH', 'group' => self::GROUP_BANK, 'type' => 'int'],
            ['key' => 'bank_inbound.reject_forwarded', 'env' => 'BANK_INBOUND_REJECT_FORWARDED', 'group' => self::GROUP_BANK, 'type' => 'bool'],
            ['key' => 'bank_inbound.mailgun_webhook_max_age_seconds', 'env' => 'MAILGUN_WEBHOOK_MAX_AGE_SECONDS', 'group' => self::GROUP_BANK, 'type' => 'int'],
            ['key' => 'bank_import.file_retention_days', 'env' => 'BANK_IMPORT_FILE_RETENTION_DAYS', 'group' => self::GROUP_BANK, 'type' => 'int'],
            ['key' => 'bank_inbound.webhook_secret', 'env' => 'BANK_INBOUND_WEBHOOK_SECRET', 'group' => self::GROUP_BANK, 'type' => 'secret', 'secret' => true],
            ['key' => 'bank_inbound.mailgun_webhook_signing_key', 'env' => 'MAILGUN_WEBHOOK_SIGNING_KEY', 'group' => self::GROUP_BANK, 'type' => 'secret', 'secret' => true],

            // Integrations & widgets
            ['key' => 'services.openregistry.enabled', 'env' => 'OPENREGISTRY_ENABLED', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'bool'],
            ['key' => 'services.openregistry.base_url', 'env' => 'OPENREGISTRY_BASE_URL', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'url'],
            ['key' => 'services.openregistry.bearer_token', 'env' => 'OPENREGISTRY_BEARER_TOKEN', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'secret', 'secret' => true],
            ['key' => 'services.matomo.url', 'env' => 'MATOMO_URL', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'url'],
            ['key' => 'services.matomo.site_id', 'env' => 'MATOMO_SITE_ID', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'string'],
            ['key' => 'services.chorala.project_key', 'env' => 'CHORALA_PROJECT_KEY', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'string'],
            ['key' => 'services.chorala.widget_url', 'env' => 'CHORALA_WIDGET_URL', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'url'],
            ['key' => 'services.chorala.use_proxy', 'env' => 'CHORALA_USE_PROXY', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'bool'],
            ['key' => 'services.chorala.end_user_jwt_secret', 'env' => 'CHORALA_END_USER_JWT_SECRET', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'secret', 'secret' => true],
            ['key' => 'services.discord.support_webhook_url', 'env' => 'SUPPORT_DISCORD_WEBHOOK_URL', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'secret', 'secret' => true],
            ['key' => 'services.stripe.tax_secret_key', 'env' => 'STRIPE_TAX_SECRET_KEY', 'group' => self::GROUP_INTEGRATIONS, 'type' => 'secret', 'secret' => true],

            // Export & mail identity
            ['key' => 'exports.signed_url_ttl', 'env' => 'EXPORT_SIGNED_URL_TTL', 'group' => self::GROUP_EXPORT, 'type' => 'int'],
            ['key' => 'mail.from.address', 'env' => 'MAIL_FROM_ADDRESS', 'group' => self::GROUP_EXPORT, 'type' => 'string'],
            ['key' => 'mail.from.name', 'env' => 'MAIL_FROM_NAME', 'group' => self::GROUP_EXPORT, 'type' => 'string'],
        ];
    }

    /**
     * @return array<string, array{env: string|null, group: string, type: string, secret: bool}>
     */
    public static function fieldMap(): array
    {
        $map = [];
        foreach (self::fields() as $field) {
            $map[$field['key']] = [
                'env' => $field['env'],
                'group' => $field['group'],
                'type' => $field['type'],
                'secret' => (bool) ($field['secret'] ?? false),
            ];
        }

        return $map;
    }

    public static function isSecret(string $key): bool
    {
        return (bool) (self::fieldMap()[$key]['secret'] ?? false);
    }

    public static function setFlagKey(string $key): string
    {
        return str_replace('.', '_', $key).'_set';
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_AUTH,
            self::GROUP_BTCPAY,
            self::GROUP_INVOICING,
            self::GROUP_GUEST,
            self::GROUP_COMPLIANCE,
            self::GROUP_RETENTION,
            self::GROUP_BANK,
            self::GROUP_INTEGRATIONS,
            self::GROUP_EXPORT,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function envToConfigKeyMap(): array
    {
        $map = [];
        foreach (self::fields() as $field) {
            if ($field['env'] !== null) {
                $map[$field['env']] = $field['key'];
            }
        }

        return $map;
    }

    public static function configKeyFromEnv(string $envKey): ?string
    {
        return self::envToConfigKeyMap()[$envKey] ?? null;
    }

    /**
     * Coerce a raw env/import value to the typed config value.
     */
    public static function coerce(string $key, mixed $raw): mixed
    {
        $type = self::fieldMap()[$key]['type'] ?? 'string';

        if ($raw === null || $raw === '') {
            return match ($type) {
                'bool' => false,
                'int' => 0,
                'nullable_int', 'nullable_string' => null,
                'csv_array' => [],
                default => '',
            };
        }

        return match ($type) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $raw,
            'nullable_int' => is_numeric($raw) ? (int) $raw : null,
            'nullable_string' => is_string($raw) && trim($raw) === '' ? null : (string) $raw,
            'csv_array' => array_values(array_filter(array_map(
                static fn (string $host): string => strtolower(trim($host)),
                explode(',', (string) $raw),
            ))),
            'url', 'string', 'uuid', 'secret' => (string) $raw,
            default => $raw,
        };
    }

    /**
     * Default from Laravel config (env fallback already baked in config files).
     */
    public static function configDefault(string $key): mixed
    {
        return config($key);
    }
}
