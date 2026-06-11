<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:10000'],
            'unit' => ['nullable', 'string', 'max:32'],
            'track_inventory' => ['sometimes', 'boolean'],
            'quantity_on_hand' => ['nullable', 'numeric', 'min:0'],
            'purchase_unit_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_currency' => ['nullable', 'string', 'size:3'],
            'sale_unit_price' => ['nullable', 'numeric', 'min:0'],
            'internal_note' => ['nullable', 'string', 'max:10000'],
            'exclude_from_suggester' => ['sometimes', 'boolean'],
        ];
    }
}
