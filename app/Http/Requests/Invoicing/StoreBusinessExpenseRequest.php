<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBusinessExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('supplier_iban'))) {
            $this->merge(['supplier_iban' => strtoupper(str_replace(' ', '', $this->input('supplier_iban')))]);
        }
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('payment_details_confirmation') !== null
                && ($this->boolean('mark_paid') || $this->input('paid_at') !== null)) {
                $validator->errors()->add('payment_details_confirmation', 'Payment details changed or existing payment evidence requires review.');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_contact_id' => ['nullable', 'uuid'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_iban' => ['nullable', 'string', 'max:34'],
            'payment_review_required' => ['nullable', 'boolean'],
            'payment_details_confirmation' => ['nullable', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'external_number' => ['nullable', 'string', 'max:64'],
            'variable_symbol' => ['nullable', 'string', 'max:32'],
            'constant_symbol' => ['nullable', 'string', 'max:16'],
            'specific_symbol' => ['nullable', 'string', 'max:16'],
            'issue_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'total' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'between:-999999999999.99,999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'mark_paid' => ['sometimes', 'boolean'],
        ];
    }
}
