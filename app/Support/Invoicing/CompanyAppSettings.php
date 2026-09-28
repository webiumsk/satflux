<?php

namespace App\Support\Invoicing;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company invoicing application preferences (SuperFaktúra-style "Aplikácia").
 */
final class CompanyAppSettings
{
    /** Plaintext key rows written before encryption at rest (migrated away). */
    public const LEGACY_STRIPE_TAX_SECRET_KEY = 'stripe_tax_secret_key';

    public const DEFAULTS = [
        'rounding_method' => 'per_line',
        'invoice_line_label' => null,
        'default_invoice_payment_terms_days' => 14,
        'default_delivery_method' => null,
        'default_delivery_date_mode' => 'empty',
        'default_payment_method' => null,
        'pdf_filename_pattern' => '#TYPE#_#COMPANY#_#NUMBER#',
        'expense_attachment_name_pattern' => null,
        'sort_lists_by' => 'issue_date',
        'number_documents_by' => 'issue_date',
        'default_constant_symbol' => '0308',
        'tax_free_minimum' => '0.00',
        'show_contextual_help' => true,
        'embed_isdoc_in_pdf' => true,
        // ZUGFeRD hybrid PDFs for DE companies (factur-x.xml in the PDF).
        'embed_zugferd_in_pdf' => true,
        'reverse_charge' => false,
        'reverse_charge_note' => null,
        // DE non-EU exports are goods (G, "Steuerfreie Ausfuhrlieferung."),
        // not services (O) - drives the EN 16931 category and default clause.
        'export_goods' => false,
        // Custom DE export clause override (defaults to the statutory wording).
        'export_note' => null,
        'us_sales_tax_provider' => 'manual',
        // Write-only secret, stored Crypt-encrypted (see stripeTaxSecretKey()).
        'stripe_tax_secret_key_encrypted' => null,
        'show_pay_by_square' => true,
        'show_invoice_by_square' => false,
        'show_client_phone_on_invoices' => false,
        'show_payme_on_invoices' => false,
        'variable_symbol_from_proforma' => false,
        'show_prices_on_delivery_notes' => false,
        'show_prices_on_orders' => true,
        'show_line_suggester' => true,
        'show_summary_on_quotes' => true,
        'runs_eshop' => false,
        'efaktura_enabled' => false,
        'efaktura_auto_send' => false,
        'efaktura_inbound_enabled' => false,
        'efaktura_provider' => 'sapi_sk',
        'efaktura_peppol_participant_id' => null,
        'efaktura_sapi_base_url' => null,
        'efaktura_sapi_client_id' => null,
        'efaktura_sapi_client_secret_encrypted' => null,
    ];

    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values = []) {}

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function from(?array $stored): self
    {
        $merged = array_merge(self::DEFAULTS, $stored ?? []);

        return new self($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Decrypted per-company Stripe Tax secret, or null when none is set.
     */
    public function stripeTaxSecretKey(): ?string
    {
        $encrypted = $this->get('stripe_tax_secret_key_encrypted');
        if (is_string($encrypted) && $encrypted !== '') {
            try {
                $secret = trim(Crypt::decryptString($encrypted));

                return $secret !== '' ? $secret : null;
            } catch (DecryptException) {
                return null;
            }
        }

        $legacy = trim((string) $this->get(self::LEGACY_STRIPE_TAX_SECRET_KEY, ''));

        return $legacy !== '' ? $legacy : null;
    }

    /**
     * Turn an incoming plaintext Stripe Tax secret into its encrypted field.
     * An empty value means "keep the current secret" (write-only field).
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function encryptIncomingStripeTaxSecret(array $incoming): array
    {
        if (! array_key_exists(self::LEGACY_STRIPE_TAX_SECRET_KEY, $incoming)) {
            return $incoming;
        }

        $secret = trim((string) $incoming[self::LEGACY_STRIPE_TAX_SECRET_KEY]);
        unset($incoming[self::LEGACY_STRIPE_TAX_SECRET_KEY]);

        if ($secret !== '') {
            $incoming['stripe_tax_secret_key_encrypted'] = Crypt::encryptString($secret);
        }

        return $incoming;
    }
}
