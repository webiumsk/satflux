<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

class BusinessDocumentCreditNoteService
{
    public function __construct(
        protected DocumentTotalsCalculator $totalsCalculator,
    ) {}

    public function createFromInvoice(Company $company, BusinessDocument $invoice): BusinessDocument
    {
        if ($invoice->company_id !== $company->id) {
            abort(404);
        }

        if ($invoice->type !== BusinessDocumentType::Invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Only invoices can be linked to a credit note.'],
            ]);
        }

        if ($invoice->status === BusinessDocumentStatus::Cancelled) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Cancelled invoices cannot be credited.'],
            ]);
        }

        if (! in_array($invoice->status, [BusinessDocumentStatus::Issued, BusinessDocumentStatus::Paid], true)) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Issue or pay the invoice before creating a credit note.'],
            ]);
        }

        if ($invoice->number === null) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The invoice must have a number before creating a credit note.'],
            ]);
        }

        $invoice->load(['lines', 'contact', 'company']);

        $noteAbove = trim(implode("\n", array_filter([
            $this->invoiceReferenceNote($invoice),
        ])));

        $document = new BusinessDocument([
            'company_id' => $company->id,
            'company_contact_id' => $invoice->company_contact_id,
            'store_id' => $invoice->store_id,
            'source_document_id' => $invoice->id,
            'type' => BusinessDocumentType::CreditNote,
            'status' => BusinessDocumentStatus::Draft,
            'title' => null,
            'variable_symbol' => $invoice->variable_symbol,
            'constant_symbol' => $invoice->constant_symbol,
            'specific_symbol' => $invoice->specific_symbol,
            'issue_date' => now()->toDateString(),
            'delivery_date' => $invoice->delivery_date?->toDateString(),
            'due_date' => now()->toDateString(),
            'currency' => $invoice->currency,
            'discount_percent' => $invoice->discount_percent,
            'note_above_lines' => $noteAbove !== '' ? $noteAbove : null,
            'note_footer' => $invoice->note_footer,
            'internal_note' => $invoice->internal_note,
            'pdf_locale' => $invoice->pdf_locale,
            'pdf_show_signature' => $invoice->pdf_show_signature,
            'pdf_show_payment_info' => false,
            'payment_btc_enabled' => false,
            'payment_bank_enabled' => false,
            'tags' => $invoice->tags,
        ]);

        $document->setRelation('company', $company);
        $lines = $invoice->lines->map(fn (BusinessDocumentLine $line) => [
            'name' => $line->name,
            'description' => $line->description,
            'quantity' => (float) $line->unit_price < 0 ? -(float) $line->quantity : (float) $line->quantity,
            'unit' => $line->unit,
            'unit_price' => abs((float) $line->unit_price),
            'line_discount_percent' => (float) $line->line_discount_percent,
            'tax_rate' => (float) $line->tax_rate,
        ])->all();

        $lineModels = collect($lines)->map(fn (array $line, int $index): BusinessDocumentLine => new BusinessDocumentLine([
            ...$line,
            'sort_order' => $index,
            'unit' => $line['unit'] ?? 'pcs',
        ]));
        $document->setRelation('lines', $lineModels);

        $this->totalsCalculator->applyToDocument(
            $document,
            $lines,
            (float) $invoice->discount_percent
        );

        $document->save();
        // applyToDocument populated these same rows with canonical gross/rates.
        $document->lines()->saveMany($lineModels);

        AuditLog::log('business_document.credit_note_from_invoice', 'business_document', $document->id, [
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
        ]);

        return $document->fresh(['lines', 'contact', 'store', 'sourceDocument']);
    }

    protected function invoiceReferenceNote(BusinessDocument $invoice): string
    {
        return sprintf(
            'K faktúre č. %s.',
            $invoice->number
        );
    }
}
