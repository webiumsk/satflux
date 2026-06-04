<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\RecurringInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRecurringProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['invoice', 'proforma'])],
            'company_contact_id' => ['nullable', 'uuid'],
            'store_id' => ['nullable', 'uuid'],
            'is_active' => ['sometimes', 'boolean'],
            'recurrence_interval' => ['required', Rule::enum(RecurringInterval::class)],
            'first_issue_date' => ['required', 'date'],
            'next_issue_date' => ['nullable', 'date'],
            'repeat_indefinitely' => ['sometimes', 'boolean'],
            'ends_at' => ['nullable', 'date'],
            'issue_last_day_of_month' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'variable_symbol' => ['nullable', 'string', 'max:32'],
            'constant_symbol' => ['nullable', 'string', 'max:10'],
            'specific_symbol' => ['nullable', 'string', 'max:10'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'delivery_date_mode' => ['nullable', Rule::in(['on_issue', 'empty'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note_above_lines' => ['nullable', 'string', 'max:10000'],
            'note_footer' => ['nullable', 'string', 'max:10000'],
            'internal_note' => ['nullable', 'string', 'max:10000'],
            'pdf_locale' => ['nullable', 'string', 'max:8'],
            'pdf_show_signature' => ['sometimes', 'boolean'],
            'pdf_show_payment_info' => ['sometimes', 'boolean'],
            'payment_btc_enabled' => ['sometimes', 'boolean'],
            'payment_bank_enabled' => ['sometimes', 'boolean'],
            'send_email_after_issue' => ['sometimes', 'boolean'],
            'email_bcc' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.line_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
