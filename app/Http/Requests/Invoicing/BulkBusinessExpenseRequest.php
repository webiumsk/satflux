<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkBusinessExpenseRequest extends FormRequest
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
                Rule::in(['mark_paid', 'cancel', 'export_xlsx', 'attachments_zip']),
            ],
            'expense_ids' => ['nullable', 'array'],
            'expense_ids.*' => ['uuid'],
            'select_all' => ['sometimes', 'boolean'],
            'filter' => ['nullable', Rule::in(['all', 'paid', 'unpaid', 'overdue'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('select_all') && empty($this->input('expense_ids'))) {
                $validator->errors()->add('expense_ids', 'Select at least one expense or use select_all.');
            }
        });
    }
}
