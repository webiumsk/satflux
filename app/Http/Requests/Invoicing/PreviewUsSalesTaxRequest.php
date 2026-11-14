<?php

namespace App\Http\Requests\Invoicing;

use App\Models\Company;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\InvoiceDeductionRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PreviewUsSalesTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_contact_id' => ['nullable', 'uuid'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['required', 'numeric', InvoiceDeductionRules::quantityRule($this, null)],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.unit_price' => ['required', 'numeric', function (string $attribute, mixed $value, Closure $fail): void {
                $company = $this->route('company');
                if (is_numeric($value) && (float) $value < 0 && $company instanceof Company
                    && ! in_array(CompanyAppSettings::from($company->getAttribute('app_settings'))->get('us_sales_tax_provider', 'manual'), ['manual', 'none'], true)) {
                    $fail('Negative unit prices require manual sales tax.');
                }
            }],
            'lines.*.line_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
