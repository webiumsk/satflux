<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Validation\Rule;

class EphemeralBusinessDocumentBtcpayRequest extends EphemeralBusinessDocumentPdfRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'store_id' => ['required', 'uuid'],
            'evolu_document_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'document.payment_btc_enabled' => ['sometimes', 'boolean'],
        ]);
    }
}
