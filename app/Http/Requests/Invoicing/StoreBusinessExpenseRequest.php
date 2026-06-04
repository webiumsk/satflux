<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessExpenseRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'external_number' => ['nullable', 'string', 'max:64'],
            'variable_symbol' => ['nullable', 'string', 'max:32'],
            'constant_symbol' => ['nullable', 'string', 'max:16'],
            'specific_symbol' => ['nullable', 'string', 'max:16'],
            'issue_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'total' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'mark_paid' => ['sometimes', 'boolean'],
        ];
    }
}
