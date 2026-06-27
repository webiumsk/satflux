<?php

namespace App\Http\Requests\Invoicing;

class EphemeralBusinessDocumentEfakturaRequest extends EphemeralBusinessDocumentPdfRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'evolu_document_id' => ['required', 'string', 'max:64'],
        ]);
    }
}
