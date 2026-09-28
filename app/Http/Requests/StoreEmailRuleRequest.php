<?php

namespace App\Http\Requests;

use App\Services\StoreEmailRuleDispatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowed = config('invoice_email_triggers', []);

        return [
            'trigger' => ['required', 'string', Rule::in($allowed)],
            'condition' => ['nullable', 'string', 'max:2000'],
            'to_addresses' => ['required', 'string', 'max:2000', $this->recipientLimit()],
            'cc_addresses' => ['nullable', 'string', 'max:2000', $this->recipientLimit()],
            'bcc_addresses' => ['nullable', 'string', 'max:2000', $this->recipientLimit()],
            'send_to_buyer' => ['sometimes', 'boolean'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:65535'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * At most StoreEmailRuleDispatcher::MAX_RECIPIENTS comma-separated entries.
     */
    protected function recipientLimit(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $entries = array_filter(array_map('trim', explode(',', (string) $value)), fn (string $entry) => $entry !== '');
            if (count($entries) > StoreEmailRuleDispatcher::MAX_RECIPIENTS) {
                $fail(__('validation.max.array', ['attribute' => $attribute, 'max' => StoreEmailRuleDispatcher::MAX_RECIPIENTS]));
            }
        };
    }

    public function payloadForModel(): array
    {
        $validated = $this->validated();

        return [
            'trigger' => $validated['trigger'],
            'condition' => $validated['condition'] ?? null,
            'to_addresses' => $validated['to_addresses'],
            'cc_addresses' => $validated['cc_addresses'] ?? null,
            'bcc_addresses' => $validated['bcc_addresses'] ?? null,
            'send_to_buyer' => $validated['send_to_buyer'] ?? false,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }
}
