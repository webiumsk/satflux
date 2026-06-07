<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\BusinessDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyDocumentSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'document_type' => ['required', 'string', Rule::in(array_column(BusinessDocumentType::cases(), 'value'))],
            'format' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
            'reset_period' => ['required', 'string', 'in:yearly,monthly,never'],
            'is_default' => ['sometimes', 'boolean'],
            'last_number' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('format')) {
            $this->merge(['format' => strtoupper((string) $this->input('format'))]);
        }
    }
}
