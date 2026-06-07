<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class SendBusinessDocumentEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['to', 'cc', 'bcc'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $parts = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $this->merge([$field => array_values($parts)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email', 'max:255'],
            'cc' => ['sometimes', 'array'],
            'cc.*' => ['email', 'max:255'],
            'bcc' => ['sometimes', 'array'],
            'bcc.*' => ['email', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
