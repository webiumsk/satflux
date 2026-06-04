<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class ValidateVatNumberRequest extends FormRequest
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
            'vat_number' => ['required', 'string', 'max:32'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
