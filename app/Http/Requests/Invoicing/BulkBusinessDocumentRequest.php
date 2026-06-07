<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkBusinessDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                Rule::in(['mark_paid', 'delete', 'cancel', 'pdf_zip', 'pdf_merge', 'export_xlsx']),
            ],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['uuid'],
            'select_all' => ['sometimes', 'boolean'],
            'filter' => ['nullable', Rule::in(['all', 'paid', 'unpaid', 'overdue'])],
            'type' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('select_all') && empty($this->input('document_ids'))) {
                $validator->errors()->add('document_ids', 'Select at least one invoice or use select_all.');
            }
        });
    }
}
