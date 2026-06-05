<?php

namespace App\Http\Requests\Invoicing;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResetCompanyDataRequest extends FormRequest
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
            'confirm_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Company $company */
            $company = $this->route('company');
            $confirm = trim((string) $this->input('confirm_name'));
            $expected = array_filter([
                trim((string) $company->legal_name),
                trim((string) ($company->trade_name ?? '')),
            ]);

            if (! in_array($confirm, $expected, true)) {
                $validator->errors()->add('confirm_name', 'Company name confirmation does not match.');
            }
        });
    }
}
