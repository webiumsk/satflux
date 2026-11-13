<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\BuyerSnapshot;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\InvoiceDeductionRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessDocumentIssueService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected BusinessDocumentPaymentTokenService $paymentTokenService,
        protected BusinessDocumentBtcPayService $btcPayService,
        protected ComplianceSubmissionService $complianceSubmissionService,
        protected CompanyStockMovementService $stockMovementService,
        protected CanonicalInvoiceBuilder $canonicalBuilder,
    ) {}

    /** @param array<int, array<string, mixed>>|null $linePayloads */
    public function assertSignedInvoiceCanIssue(BusinessDocument $document, ?array $linePayloads = null): void
    {
        $document->loadMissing(['company', 'contact', 'lines']);
        $rows = $linePayloads ?? $document->lines->map(fn ($line): array => $line->getAttributes())->all();
        $hasDeduction = InvoiceDeductionRules::hasDeduction($rows);
        if (! $hasDeduction) {
            return;
        }
        $amountPaid = (float) $document->amount_paid;
        if (! is_finite($amountPaid) || $amountPaid > 999_999_999_999.99
            || collect($rows)->contains(function (array $line): bool {
                $quantity = (float) ($line['quantity'] ?? 0);
                $price = (float) ($line['unit_price'] ?? 0);

                return ! is_finite($quantity) || $quantity === 0.0 || abs($quantity) > 9_999_999_999.9999
                    || ($quantity < 0 && ($price < 0.01 || abs($quantity) < 0.0001 || ! empty($line['company_stock_item_id']) || ! empty($line['company_warehouse_id'])))
                    || abs($quantity * 10000 - round($quantity * 10000)) > 0.000001
                    || abs($price * 100 - round($price * 100)) > 0.000001
                    || ! is_finite($price) || abs($price) > 999_999_999_999.99
                    || abs($quantity * $price) > 999_999_999_999.99;
            })) {
            throw ValidationException::withMessages(['lines' => ['Invoice amounts exceed the supported range.']]);
        }
        /** @var Company $company */
        $company = $document->getRelation('company');
        $hasNegativePrice = collect($rows)->contains(fn (array $line): bool => (float) ($line['unit_price'] ?? 0) < 0);
        $supportedType = $document->getAttribute('type') === BusinessDocumentType::Invoice
            || ($document->getAttribute('type') === BusinessDocumentType::CreditNote && ! $hasNegativePrice);
        if (! $supportedType
            || ! in_array($company->getAttribute('jurisdiction'), [CompanyJurisdiction::EuSk, CompanyJurisdiction::EuCz, CompanyJurisdiction::Us], true)
            || ($company->getAttribute('jurisdiction') === CompanyJurisdiction::Us
                && ! in_array(CompanyAppSettings::from($company->getAttribute('app_settings'))->get('us_sales_tax_provider', 'manual'), ['manual', 'none'], true))) {
            throw ValidationException::withMessages(['lines' => ['Negative items are unsupported for this document type or invoice provider.']]);
        }
        $canonical = $linePayloads === null
            ? $this->canonicalBuilder->fromDocument($document)
            : $this->canonicalBuilder->fromLinePayloads($company, $linePayloads, (float) $document->discount_percent, $document, $document->resolvedBuyer());
        if (! is_finite((float) $canonical->total) || (float) $canonical->total <= 0
            || (float) $canonical->total > 999_999_999_999.99 || $amountPaid > (float) $canonical->total
            || $canonical->subtotal !== (string) $document->subtotal || $canonical->taxTotal !== (string) $document->tax_total
            || $canonical->total !== (string) $document->total
            || (float) $canonical->subtotal <= 0 || (float) $canonical->taxTotal < 0
            || collect($canonical->taxBreakdown)->contains(fn ($row): bool => (float) $row->taxableAmount < 0 || (float) $row->taxAmount < 0)) {
            throw ValidationException::withMessages(['lines' => ['An invoice with deductions must have positive totals and nonnegative tax bases.']]);
        }
    }

    public function issue(BusinessDocument $document): BusinessDocument
    {
        // Lock + re-check INSIDE the transaction: two concurrent issue calls
        // both passing an unlocked canIssue() would burn two numbers and
        // apply stock twice (Cursor PR #64).
        $issued = DB::transaction(function () use ($document) {
            $document = BusinessDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $document->load(['company', 'lines', 'store', 'contact']);

            if (! $document->canIssue()) {
                throw ValidationException::withMessages([
                    'status' => ['Document cannot be issued in its current status.'],
                ]);
            }

            $this->assertSignedInvoiceCanIssue($document);

            $number = $this->sequenceService->nextNumber(
                $document->company,
                $document->type->value
            );

            $document->number = $number;
            $document->variable_symbol = BankSymbolNormalizer::variableSymbol($document->variable_symbol)
                ?? BankSymbolNormalizer::variableSymbol($number);
            $document->issue_date = $document->issue_date ?? now()->toDateString();
            $document->status = BusinessDocumentStatus::Issued;

            if ($document->contact) {
                $document->buyer_snapshot = BuyerSnapshot::fromContact($document->contact);
            }

            if ($document->type === BusinessDocumentType::Quote) {
                $document->quote_status = BusinessDocumentQuoteStatus::Pending;
            }

            $document->btcpay_invoice_id = null;
            $document->btcpay_checkout_link = null;
            $document->btcpay_checkout_created_at = null;
            $this->paymentTokenService->assignIfNeeded($document);

            $document->save();

            if ($document->payment_btc_enabled && $document->store_id) {
                try {
                    $this->btcPayService->syncForDocument($document->fresh(), forceRefresh: true);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            AuditLog::log('business_document.issued', 'business_document', $document->id, [
                'company_id' => $document->company_id,
                'number' => $document->number,
            ]);

            $document = $document->fresh(['lines', 'contact', 'store', 'company']);
            $this->stockMovementService->applyDocumentIssue($document);

            return $document;
        });

        $this->complianceSubmissionService->queueIfEligible($issued);

        // The transaction worked on a locked re-fetch - sync the caller's
        // instance so the pre-lock contract (in-place mutation) holds.
        $document->refresh();

        return $issued;
    }
}
