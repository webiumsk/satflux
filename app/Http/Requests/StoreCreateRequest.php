<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'default_currency' => ['required', 'string', 'max:10'], // Allow BTC, SATS, and 3-letter codes
            'timezone' => ['required', 'string', 'timezone'],
            'preferred_exchange' => ['nullable', 'string', 'max:255'],
            'wallet_type' => ['required', 'string', Rule::in(['blink', 'aqua_boltz'])],
            'connection_string' => ['nullable', 'string', 'max:2000'], // Connection string or descriptor
        ];
    }
}








