<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Support\Invoicing\CompanyVatPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessDocumentFromQuoteService
{
    public function __construct(
        protected DocumentTotalsCalculator $totalsCalculator,
        protected BusinessDocumentIssueService $issueService,
    ) {}

    public function createInvoiceFromQuote(Company $company, BusinessDocument $quote): BusinessDocument
    {
        if ($quote->company_id !== $company->id) {
            abort(404);
        }

        if ($quote->type !== BusinessDocumentType::Quote) {
            throw ValidationException::withMessages([
                'type' => ['Only quotes can be converted to an invoice.'],
            ]);
        }

        if ($quote->status !== BusinessDocumentStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => ['Issue the quote before creating an invoice.'],
            ]);
        }

        if ($quote->number === null) {
            throw ValidationException::withMessages([
                'status' => ['Issue the quote before creating an invoice.'],
            ]);
        }

        $resolved = $quote->resolvedQuoteStatus();
        if ($resolved !== BusinessDocumentQuoteStatus::Approved) {
            throw ValidationException::withMessages([
                'quote_status' => ['Approve the quote before creating an invoice.'],
            ]);
        }

        // Check + create + issue under a lock on the quote: a double submit
        // used to create (and number) two invoices; a failed issue rolls the
        // draft back instead of blocking every retry with "already exists".
        return DB::transaction(function () use ($company, $quote) {
            // Re-check against the LOCKED row - the checks above read a copy
            // that may have been rejected, cancelled or deleted since.
            $quote = BusinessDocument::query()->whereKey($quote->id)->lockForUpdate()->first();
            if ($quote === null || $quote->company_id !== $company->id) {
                abort(404);
            }
            if ($quote->typeValue() !== BusinessDocumentType::Quote->value) {
                throw ValidationException::withMessages([
                    'type' => ['Only quotes can be converted to an invoice.'],
                ]);
            }
            if (! $quote->hasStatus(BusinessDocumentStatus::Issued) || ! $quote->hasNumber()) {
                throw ValidationException::withMessages([
                    'status' => ['Issue the quote before creating an invoice.'],
                ]);
            }
            if ($quote->resolvedQuoteStatus() !== BusinessDocumentQuoteStatus::Approved) {
                throw ValidationException::withMessages([
                    'quote_status' => ['Approve the quote before creating an invoice.'],
                ]);
            }

            $existing = BusinessDocument::query()
                ->where('source_document_id', $quote->id)
                ->where('type', BusinessDocumentType::Invoice)
                ->where('status', '!=', BusinessDocumentStatus::Cancelled)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'source_document_id' => ['An invoice already exists for this quote.'],
                ]);
            }

            $quote->load(['lines', 'contact', 'company']);

            $noteAbove = trim(implode("\n", array_filter([
                $quote->note_above_lines,
                $this->quoteReferenceNote($quote),
            ])));

            $document = new BusinessDocument([
                'company_id' => $company->id,
                'company_contact_id' => $quote->company_contact_id,
                'store_id' => $quote->store_id,
                'source_document_id' => $quote->id,
                'type' => BusinessDocumentType::Invoice,
                'status' => BusinessDocumentStatus::Draft,
                'title' => null,
                // The quote's VS is the quote number's digits - issue() derives
                // the invoice's own from its number (bank matching keys on it).
                'variable_symbol' => null,
                'constant_symbol' => $quote->constant_symbol,
                'specific_symbol' => $quote->specific_symbol,
                'issue_date' => now()->toDateString(),
                'delivery_date' => $quote->delivery_date?->toDateString(),
                'due_date' => now()->addDays($this->paymentTermsDays($quote))->toDateString(),
                'currency' => $quote->currency,
                'discount_percent' => $quote->discount_percent,
                'note_above_lines' => $noteAbove !== '' ? $noteAbove : null,
                'note_footer' => $quote->note_footer,
                'internal_note' => $quote->internal_note,
                'pdf_locale' => $quote->pdf_locale,
                'pdf_show_signature' => $quote->pdf_show_signature,
                // Quotes are stored with payment off; an invoice asks for payment
                // (the invoice defaults of store()).
                'pdf_show_payment_info' => true,
                'payment_btc_enabled' => false,
                'payment_bank_enabled' => true,
                'tags' => $quote->tags,
            ]);

            $document->setRelation('company', $company);
            $lines = $quote->lines->map(fn (BusinessDocumentLine $line) => [
                'name' => $line->name,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (float) $line->unit_price,
                'line_discount_percent' => (float) $line->line_discount_percent,
                'tax_rate' => (float) $line->tax_rate,
                // The quote / proforma moved no stock; the invoice does on issue.
                'company_stock_item_id' => $line->company_stock_item_id,
                'company_warehouse_id' => $line->company_warehouse_id,
            ])->all();

            $this->totalsCalculator->applyToDocument(
                $document,
                $lines,
                (float) $quote->discount_percent
            );

            $document->save();

            foreach ($lines as $index => $line) {
                $qty = (float) ($line['quantity'] ?? 1);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $lineDiscount = (float) ($line['line_discount_percent'] ?? 0);
                $taxRate = (float) ($line['tax_rate'] ?? 0);
                $lineNet = $qty * $unitPrice * (1 - $lineDiscount / 100);
                $buyer = $document->resolvedBuyer();
                $vatPolicy = app(CompanyVatPolicy::class);
                $taxRate = $vatPolicy->resolveLineTaxRate($company, $buyer, $taxRate);
                $lineTax = $vatPolicy->calculatesVatAmounts($company, $buyer) ? $lineNet * ($taxRate / 100) : 0;

                BusinessDocumentLine::create([
                    'business_document_id' => $document->id,
                    'company_stock_item_id' => $line['company_stock_item_id'] ?? null,
                    'company_warehouse_id' => $line['company_warehouse_id'] ?? null,
                    'sort_order' => $index,
                    'name' => $line['name'],
                    'description' => $line['description'] ?? null,
                    'quantity' => $qty,
                    'unit' => $line['unit'] ?? 'pcs',
                    'unit_price' => $unitPrice,
                    'line_discount_percent' => $lineDiscount,
                    'tax_rate' => $taxRate,
                    'line_total' => number_format($lineNet + $lineTax, 2, '.', ''),
                ]);
            }

            $document = $this->issueService->issue($document);

            $document->update([
                'title' => $this->invoiceTitle($document->number),
            ]);

            AuditLog::log('business_document.invoice_from_quote', 'business_document', $document->id, [
                'company_id' => $company->id,
                'quote_id' => $quote->id,
                'quote_number' => $quote->number,
                'invoice_number' => $document->number,
            ]);

            return $document->fresh(['lines', 'contact', 'store', 'sourceDocument']);

        });
    }

    protected function invoiceTitle(string $number): string
    {
        return 'Faktúra '.$number;
    }

    protected function paymentTermsDays(BusinessDocument $quote): int
    {
        if ($quote->relationLoaded('contact') && $quote->contact) {
            return (int) $quote->contact->default_payment_terms_days;
        }

        return 14;
    }

    protected function quoteReferenceNote(BusinessDocument $quote): string
    {
        return sprintf(
            'Podľa cenovej ponuky č. %s.',
            $quote->number
        );
    }
}
