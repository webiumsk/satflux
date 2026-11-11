<?php

namespace App\Http\Requests\Invoicing;

class EphemeralBusinessDocumentPdfRenderRequest extends EphemeralBusinessDocumentPdfRequest
{
    public function rules(): array
    {
        $amountRules = ['sometimes', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/'];

        return array_replace(parent::rules(), [
            'lines.*.unit_price' => ['required', 'numeric'],
            'source_totals' => ['sometimes', 'array:subtotal,tax_total,total,line_totals'],
            'source_totals.subtotal' => $amountRules,
            'source_totals.tax_total' => $amountRules,
            'source_totals.total' => $amountRules,
            'source_totals.line_totals' => ['sometimes', 'array', 'list'],
            'source_totals.line_totals.*' => ['string', 'regex:/\A-?(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/'],
        ]);
    }
}
