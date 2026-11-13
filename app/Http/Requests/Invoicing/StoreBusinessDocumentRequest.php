<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\InvoiceDeductionRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('variable_symbol')) {
            $this->merge([
                'variable_symbol' => BankSymbolNormalizer::variableSymbol($this->input('variable_symbol')),
            ]);
        }
    }

    private function deductionPrecision(int $places, ?float $maximum = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($places, $maximum): void {
            $hasDeduction = InvoiceDeductionRules::hasDeduction((array) $this->input('lines', []));
            if ($hasDeduction && is_numeric($value)
                && (! is_finite((float) $value) || ($maximum !== null && abs((float) $value) > $maximum)
                    || abs((float) $value * (10 ** $places) - round((float) $value * (10 ** $places))) > 0.000001)) {
                $fail('Invoice deductions require values within the supported decimal precision.');
            }
        };
    }

    public function rules(): array
    {
        return [
            'type' => [
                Rule::requiredIf($this->isMethod('POST')),
                Rule::enum(BusinessDocumentType::class),
            ],
            'company_contact_id' => ['nullable', 'uuid'],
            'store_id' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:255'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'constant_symbol' => ['nullable', 'string', 'max:10'],
            'specific_symbol' => ['nullable', 'string', 'max:10'],
            'issue_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', $this->deductionPrecision(2)],
            'note_above_lines' => ['nullable', 'string', 'max:10000'],
            'note_footer' => ['nullable', 'string', 'max:10000'],
            'internal_note' => ['nullable', 'string', 'max:10000'],
            'pdf_locale' => ['nullable', 'string', 'max:8'],
            'pdf_bank_qr' => ['nullable', 'in:auto,paybysquare,epc,swiss,none'],
            'pdf_show_signature' => ['sometimes', 'boolean'],
            'pdf_show_payment_info' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'payment_btc_enabled' => ['sometimes', 'boolean'],
            'payment_bank_enabled' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['required', 'numeric', InvoiceDeductionRules::quantityRule($this, 'type'), $this->deductionPrecision(4, 9_999_999_999.9999)],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.unit_price' => ['required', 'numeric', function (string $attribute, mixed $value, Closure $fail): void {
                $hasDeduction = InvoiceDeductionRules::hasDeduction((array) $this->input('lines', []));
                if ($hasDeduction && is_numeric($value) && (! is_finite((float) $value) || abs((float) $value) > 999_999_999_999.99
                    || abs((float) $value * 100 - round((float) $value * 100)) > 0.000001)) {
                    $fail('Invoices with deductions require unit prices with at most two decimal places.');

                    return;
                }
                if (! is_numeric($value) || (float) $value >= 0) {
                    return;
                }
                $type = $this->input('type') ?? $this->route('businessDocument')?->type?->value;
                if ($type !== BusinessDocumentType::Invoice->value) {
                    $fail('Negative unit prices are supported only on invoices.');

                    return;
                }
                $company = $this->route('company');
                if ($company instanceof Company && ($company->getAttribute('jurisdiction') === CompanyJurisdiction::EuDe
                    || ($company->getAttribute('jurisdiction') === CompanyJurisdiction::Us
                        && ! in_array(CompanyAppSettings::from($company->getAttribute('app_settings'))->get('us_sales_tax_provider', 'manual'), ['manual', 'none'], true)))) {
                    $fail('Negative unit prices are not supported by the configured electronic invoice or tax provider.');
                }
            }],
            'lines.*.line_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', $this->deductionPrecision(2)],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', $this->deductionPrecision(2)],
            'lines.*.company_stock_item_id' => ['nullable', 'uuid'],
            'lines.*.company_warehouse_id' => ['nullable', 'uuid'],
        ];
    }
}
