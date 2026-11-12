<?php

namespace App\Http\Requests\Invoicing;

class EphemeralBusinessDocumentBulkPdfRenderRequest extends EphemeralBusinessDocumentBulkRequest
{
    public function rules(): array
    {
        $amountRules = ['sometimes', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/'];

        return array_replace(parent::rules(), [
            'documents.*.lines.*.unit_price' => ['required', 'numeric'],
            'documents.*.source_totals' => ['sometimes', 'array:subtotal,tax_total,total,line_totals'],
            'documents.*.source_totals.subtotal' => $amountRules,
            'documents.*.source_totals.tax_total' => $amountRules,
            'documents.*.source_totals.total' => $amountRules,
            'documents.*.source_totals.line_totals' => ['sometimes', 'array', 'list'],
            'documents.*.source_totals.line_totals.*' => ['string', 'regex:/\A-?(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/'],
        ]);
    }
}
