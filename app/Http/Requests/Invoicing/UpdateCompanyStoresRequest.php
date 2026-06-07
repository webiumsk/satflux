<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyStoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_ids' => ['present', 'array', 'max:1'],
            'store_ids.*' => ['uuid', 'exists:stores,id'],
        ];
    }
}
