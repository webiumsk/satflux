<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $days = $this->input('default_payment_terms_days');
        if ($days === null || $days === '') {
            $this->merge(['default_payment_terms_days' => 14]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'fax' => ['nullable', 'string', 'max:64'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'peppol_participant_id' => ['nullable', 'string', 'max:64'],
            'vat_id' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'state_region' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'max:64'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'iban' => ['nullable', 'string', 'max:64'],
            'swift' => ['nullable', 'string', 'max:16'],
            'delivery_street' => ['nullable', 'string', 'max:255'],
            'delivery_postal_code' => ['nullable', 'string', 'max:32'],
            'delivery_city' => ['nullable', 'string', 'max:128'],
            'delivery_country' => ['nullable', 'string', 'max:64'],
            'default_payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'contact_persons' => ['nullable', 'array'],
            'contact_persons.*.name' => ['nullable', 'string', 'max:255'],
            'contact_persons.*.phone' => ['nullable', 'string', 'max:64'],
            'contact_persons.*.email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
