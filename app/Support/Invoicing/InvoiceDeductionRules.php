<?php

namespace App\Support\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\Company;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Validation shared by ordinary invoice and ephemeral deduction inputs. */
class InvoiceDeductionRules
{
    /** @param array<array-key, mixed> $lines */
    public static function hasDeduction(array $lines): bool
    {
        foreach ($lines as $line) {
            if (is_array($line) && ((is_numeric($line['quantity'] ?? null) && (float) $line['quantity'] < 0)
                || (is_numeric($line['unit_price'] ?? null) && (float) $line['unit_price'] < 0))) {
                return true;
            }
        }

        return false;
    }

    public static function quantityRule(FormRequest $request, ?string $typePath = 'document.type'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request, $typePath): void {
            if (! is_numeric($value)) {
                return;
            }
            $quantity = (float) $value;
            if (! is_finite($quantity) || $quantity === 0.0 || ($typePath === 'type' && abs($quantity) < 0.0001)) {
                $fail('Invoice quantity must be a finite nonzero number.');

                return;
            }
            if ($quantity > 0) {
                return;
            }
            $linePath = substr($attribute, 0, -strlen('.quantity'));
            $prefix = substr($linePath, 0, (int) strpos($linePath, 'lines.'));
            $line = (array) $request->input($linePath, []);
            $existing = $request->route('businessDocument');
            $existingType = $existing instanceof BusinessDocument ? $existing->getAttribute('type')?->value : null;
            $type = $typePath === null ? BusinessDocumentType::Invoice->value : $request->input($prefix.$typePath, $existingType);
            $company = $request->route('company');
            $snapshotJurisdiction = $request->input('company.jurisdiction', 'eu_sk');
            $jurisdiction = ($typePath === 'type' || $typePath === null) && $company instanceof Company
                ? JurisdictionRules::normalizeValue($company->getAttribute('jurisdiction'))
                : (is_string($snapshotJurisdiction) ? $snapshotJurisdiction : '');
            $snapshotSettings = $request->input('company.app_settings', []);
            $settings = $company instanceof Company && ($typePath === 'type' || $typePath === null)
                ? CompanyAppSettings::from($company->getAttribute('app_settings'))
                : CompanyAppSettings::from(is_array($snapshotSettings) ? $snapshotSettings : []);
            $price = (float) ($line['unit_price'] ?? 0);
            if (! in_array($type, [BusinessDocumentType::Invoice->value, BusinessDocumentType::CreditNote->value], true)
                || ! in_array($jurisdiction, ['eu_sk', 'eu_cz', 'us'], true)
                || ($jurisdiction === 'us' && ! in_array($settings->get('us_sales_tax_provider', 'manual'), ['manual', 'none'], true))
                || ! is_finite($price) || $price < 0.01 || abs($price * 100 - round($price * 100)) > 0.000001
                || abs($quantity) < 0.0001
                || abs($quantity * 10000 - round($quantity * 10000)) > 0.000001
                || abs($quantity) > 9_999_999_999.9999 || abs($quantity * $price) > 999_999_999_999.99
                || ! empty($line['company_stock_item_id']) || ! empty($line['company_warehouse_id'])) {
                $fail('Negative quantities require a supported invoice or credit note, a positive unit price and a manual item without stock links.');
            }
        };
    }
}
