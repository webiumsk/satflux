<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCompanyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['export_xlsx', 'delete'])],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['uuid'],
            'select_all' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
            'letter' => ['nullable', 'string', 'max:8'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('select_all') && empty($this->input('contact_ids'))) {
                $validator->errors()->add('contact_ids', 'Select at least one contact or use select_all.');
            }
        });
    }
}
