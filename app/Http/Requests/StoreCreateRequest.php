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
            'wallet_type' => ['required', 'string', Rule::in(['blink', 'aqua_boltz', 'cashu'])],

            // Blink/Aqua wallet connection string (Blink token or Aqua descriptor).
            'connection_string' => [
                'nullable',
                'string',
                'max:2000',
                // Cashu doesn't use wallet_connections secrets; it's configured directly via BTCPay Cashu plugin.
                'prohibited_if:wallet_type,cashu',
            ],

            // Cashu plugin settings.
            'mint_url' => ['required_if:wallet_type,cashu', 'string', 'url', 'starts_with:https://'],
            'unit' => ['required_if:wallet_type,cashu', 'string', Rule::in(['sat', 'usd'])],
            'lightning_address' => ['required_if:wallet_type,cashu', 'string', 'regex:/^[^@]+@[^@]+$/'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If connection_string is provided, validate it matches the wallet_type
            if ($this->filled('connection_string') && $this->filled('wallet_type')) {
                $connectionString = $this->connection_string;
                $walletType = $this->wallet_type;

                $connectionValidator = app(\App\Services\WalletConnectionValidator::class);

                if ($walletType === 'blink') {
                    // Validate Blink connection string format
                    $validation = $connectionValidator->validate('blink', $connectionString);
                    if (!$validation['valid']) {
                        $errors = $validation['errors'] ?? ['Invalid Blink connection string format. Expected: type=blink;server=https://...;api-key=...;wallet-id=...'];
                        foreach ($errors as $error) {
                            $validator->errors()->add('connection_string', $error);
                        }
                    }
                } elseif ($walletType === 'aqua_boltz') {
                    // Validate Aqua descriptor format
                    $validation = $connectionValidator->validate('aqua_descriptor', $connectionString);
                    if (!$validation['valid']) {
                        $errors = $validation['errors'] ?? ['Invalid descriptor format. Must be a valid Aqua wallet output descriptor (e.g., wpkh(), tr(), wsh(), or complex formats like ct(slip77(...),elsh(wpkh(...)))) and must not contain private keys.'];
                        foreach ($errors as $error) {
                            $validator->errors()->add('connection_string', $error);
                        }
                    }
                } elseif ($walletType === 'cashu') {
                    // connection_string is prohibited by validation rules; no extra validation needed.
                }
            }
        });
    }
}








