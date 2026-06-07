<?php

namespace App\Http\Requests\Invoicing;

use App\Support\Invoicing\CompanyEmailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyEmailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_method' => ['sometimes', 'string', Rule::in([
                CompanyEmailSettings::DELIVERY_SYSTEM,
                CompanyEmailSettings::DELIVERY_SMTP,
                CompanyEmailSettings::DELIVERY_GMAIL,
                CompanyEmailSettings::DELIVERY_OFFICE,
            ])],
            'smtp' => ['sometimes', 'array'],
            'smtp.username' => ['nullable', 'string', 'max:255'],
            'smtp.password' => ['nullable', 'string', 'max:255'],
            'smtp.host' => ['nullable', 'string', 'max:255'],
            'smtp.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp.from_name' => ['nullable', 'string', 'max:128'],
            'smtp.encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'smtp.use_smtp_email_as_from' => ['sometimes', 'boolean'],
            'templates' => ['sometimes', 'array'],
            'templates.*.subject' => ['nullable', 'string', 'max:500'],
            'templates.*.body' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
