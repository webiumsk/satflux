<?php

namespace App\Support\Invoicing;

/**
 * Localized display labels for invoice line units stored in SuperFaktura-compatible form (ks., hod., …).
 */
final class InvoiceUnitLabel
{
    /** @var array<string, string> Stored unit value => lang JSON key */
    private const PRESET_KEYS = [
        'ks.' => 'invoice_unit_pcs',
        'ks' => 'invoice_unit_pcs',
        'hod.' => 'invoice_unit_hours',
        'hod' => 'invoice_unit_hours',
        'mesiace' => 'invoice_unit_months',
        'person' => 'invoice_unit_person',
        'rok' => 'invoice_unit_year',
    ];

    public static function format(?string $unit): string
    {
        if ($unit === null || trim($unit) === '') {
            return '';
        }

        $normalized = trim($unit);
        $key = self::PRESET_KEYS[$normalized] ?? null;

        return $key !== null ? __($key) : $normalized;
    }
}
