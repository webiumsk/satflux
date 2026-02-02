<?php

namespace App\Http\Requests;

use App\Services\WalletConnectionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class WalletConnectionStoreRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:blink,aqua_descriptor,nwc'],
            'secret' => ['required', 'string', 'min:10'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $secret = $this->input('secret');

            if ($type && $secret && in_array($type, ['blink', 'aqua_descriptor', 'nwc'], true)) {
                $connectionValidator = new WalletConnectionValidator();
                $validation = $connectionValidator->validate($type, $secret);

                if (! $validation['valid']) {
                    foreach ($validation['errors'] as $error) {
                        $validator->errors()->add('secret', $error);
                    }
                }
            }
        });
    }
}


