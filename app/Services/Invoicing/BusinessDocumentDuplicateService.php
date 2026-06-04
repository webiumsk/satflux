<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;

class BusinessDocumentDuplicateService
{
    public function duplicate(Company $company, BusinessDocument $source): BusinessDocument
    {
        $source->load('lines');

        $copy = $source->replicate([
            'number',
            'variable_symbol',
            'source_document_id',
            'quote_status',
            'btcpay_invoice_id',
            'btcpay_checkout_link',
            'payment_token',
            'btcpay_checkout_created_at',
            'paid_at',
            'amount_paid',
        ]);
        $copy->company_id = $company->id;
        $copy->status = BusinessDocumentStatus::Draft;
        $copy->issue_date = now()->toDateString();
        $paymentDays = $source->relationLoaded('contact') && $source->contact
            ? (int) $source->contact->default_payment_terms_days
            : 14;
        $copy->due_date = now()->addDays($paymentDays)->toDateString();
        $copy->save();

        foreach ($source->lines as $line) {
            BusinessDocumentLine::create([
                'business_document_id' => $copy->id,
                'sort_order' => $line->sort_order,
                'name' => $line->name,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'line_discount_percent' => $line->line_discount_percent,
                'tax_rate' => $line->tax_rate,
                'line_total' => $line->line_total,
            ]);
        }

        return $copy->fresh(['lines', 'contact', 'store']);
    }
}
