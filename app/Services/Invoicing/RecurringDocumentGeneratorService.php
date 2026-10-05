<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\BusinessRecurringProfile;
use App\Models\BusinessRecurringProfileLine;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\CompanyVatPolicy;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class RecurringDocumentGeneratorService
{
    public function __construct(
        protected RecurringPlaceholderResolver $placeholders,
        protected RecurringNextDateCalculator $nextDateCalculator,
        protected BusinessDocumentIssueService $issueService,
        protected DocumentSequenceService $sequenceService,
    ) {}

    public function generateDueProfiles(?Carbon $today = null): int
    {
        $today = $today ?? Carbon::today();
        $count = 0;

        BusinessRecurringProfile::query()
            ->where('is_active', true)
            ->whereDate('next_issue_date', '<=', $today->toDateString())
            ->with(['company', 'lines', 'contact'])
            ->orderBy('next_issue_date')
            ->chunkById(50, function ($profiles) use ($today, &$count) {
                foreach ($profiles as $profile) {
                    if (! $this->nextDateCalculator->isDue($profile, $today)) {
                        continue;
                    }

                    try {
                        $this->generateForProfile($profile, $today);
                        $count++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        return $count;
    }

    /**
     * One generation per profile at a time: a double click on "generate now"
     * or a click racing the daily run used to issue two documents for the
     * same period. The run re-checks next_issue_date under the lock - a
     * concurrent run that already advanced the profile wins.
     */
    public function generateForProfile(BusinessRecurringProfile $profile, ?Carbon $issueDate = null): BusinessDocument
    {
        $lock = Cache::lock('recurring-profile-generate:'.$profile->getKey(), 120);
        try {
            $lock->block(30);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'profile' => ['This recurring profile is already being generated.'],
            ]);
        }

        try {
            // Generate from the state read UNDER the lock, not the caller's
            // possibly stale copy.
            $current = BusinessRecurringProfile::query()
                ->whereKey($profile->getKey())
                ->with(['company', 'lines', 'contact'])
                ->first();
            if ($current === null
                || $this->dateString($current->next_issue_date) !== $this->dateString($profile->getOriginal('next_issue_date'))) {
                throw ValidationException::withMessages([
                    'profile' => ['This recurring profile was generated in the meantime - reload it.'],
                ]);
            }
            if (! $current->is_active) {
                throw ValidationException::withMessages([
                    'profile' => ['This recurring profile is not active.'],
                ]);
            }

            $document = $this->generateUnlocked($current, $issueDate);
            $profile->setRawAttributes($current->getAttributes(), true);

            return $document;
        } finally {
            $lock->release();
        }
    }

    protected function dateString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse($value)->toDateString();
    }

    protected function generateUnlocked(BusinessRecurringProfile $profile, ?Carbon $issueDate = null): BusinessDocument
    {
        $profile->loadMissing(['company', 'lines', 'contact']);
        $issueDate = $issueDate ?? Carbon::parse($profile->next_issue_date);
        $docType = $profile->document_type === 'proforma'
            ? BusinessDocumentType::Proforma
            : BusinessDocumentType::Invoice;

        $previewNumber = $this->sequenceService->previewNextNumber($profile->company, $docType->value);
        $vsTemplate = $profile->variable_symbol ?: '#VARIABLE_SYMBOL#';

        $issueDateStr = $issueDate->toDateString();
        $dueDate = $issueDate->copy()->addDays($profile->payment_terms_days)->toDateString();
        $deliveryDate = match ($profile->delivery_date_mode) {
            'on_issue' => $issueDateStr,
            default => null,
        };

        $document = new BusinessDocument([
            'company_id' => $profile->company_id,
            'company_contact_id' => $profile->company_contact_id,
            'store_id' => $profile->store_id,
            'type' => $docType,
            'status' => BusinessDocumentStatus::Draft,
            'title' => $this->placeholders->resolve($profile->title, $issueDate, $previewNumber, $vsTemplate),
            'variable_symbol' => BankSymbolNormalizer::variableSymbol(
                $this->placeholders->resolve($vsTemplate, $issueDate, $previewNumber, $vsTemplate)
            ) ?? BankSymbolNormalizer::variableSymbol($previewNumber),
            'constant_symbol' => $profile->constant_symbol,
            'specific_symbol' => $profile->specific_symbol,
            'issue_date' => $issueDateStr,
            'delivery_date' => $deliveryDate,
            'due_date' => $dueDate,
            'currency' => $profile->currency,
            'discount_percent' => $profile->discount_percent,
            'note_above_lines' => $this->placeholders->resolve($profile->note_above_lines, $issueDate, $previewNumber, $vsTemplate),
            'note_footer' => $profile->note_footer,
            'internal_note' => $profile->internal_note,
            'pdf_locale' => $profile->pdf_locale,
            'pdf_show_signature' => $profile->pdf_show_signature,
            'pdf_show_payment_info' => $profile->pdf_show_payment_info,
            'payment_btc_enabled' => $profile->payment_btc_enabled,
            'payment_bank_enabled' => $profile->payment_bank_enabled,
            'tags' => $profile->tags,
        ]);

        $document->setRelation('company', $profile->company);
        $linePayloads = $profile->lines->map(fn ($line) => [
            'name' => $this->placeholders->resolve($line->name, $issueDate, $previewNumber, $vsTemplate),
            'description' => $this->placeholders->resolve($line->description, $issueDate, $previewNumber, $vsTemplate),
            'quantity' => (float) $line->quantity,
            'unit' => $line->unit,
            'unit_price' => (float) $line->unit_price,
            'line_discount_percent' => (float) $line->line_discount_percent,
            'tax_rate' => (float) $line->tax_rate,
        ])->all();

        app(DocumentTotalsCalculator::class)->applyToDocument(
            $document,
            $linePayloads,
            (float) $profile->discount_percent
        );

        $document->save();

        foreach ($linePayloads as $index => $line) {
            $qty = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $lineDiscount = (float) ($line['line_discount_percent'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $lineNet = $qty * $unitPrice * (1 - $lineDiscount / 100);
            $buyer = $profile->contact;
            $vatPolicy = app(CompanyVatPolicy::class);
            $taxRate = $vatPolicy->resolveLineTaxRate($profile->company, $buyer, $taxRate);
            $lineTax = $vatPolicy->calculatesVatAmounts($profile->company, $buyer) ? $lineNet * ($taxRate / 100) : 0;

            BusinessDocumentLine::create([
                'business_document_id' => $document->id,
                'sort_order' => $index,
                'name' => $line['name'],
                'description' => $line['description'] ?? null,
                'quantity' => $qty,
                'unit' => $line['unit'] ?? 'ks',
                'unit_price' => $unitPrice,
                'line_discount_percent' => $lineDiscount,
                'tax_rate' => $taxRate,
                'line_total' => number_format($lineNet + $lineTax, 2, '.', ''),
            ]);
        }

        try {
            $issued = $this->issueService->issue($document);
        } catch (\Throwable $e) {
            // The profile is not advanced on failure, so every daily run used
            // to leave another unissued draft behind. Only a document that is
            // still a draft goes: issue() can throw after committing (e.g.
            // the e-Faktura queueing), and an issued one must stay.
            $persisted = BusinessDocument::query()->whereKey($document->getKey())->first();
            if ($persisted !== null && $persisted->hasStatus(BusinessDocumentStatus::Draft)) {
                $persisted->lines()->delete();
                $persisted->delete();
            } elseif ($persisted !== null) {
                // Issued, then failed: the period IS generated - advance the
                // profile so a retry does not issue the same period twice.
                $profile->last_generated_document_id = $persisted->id;
                $profile->last_generated_at = now();
                $profile->next_issue_date = $this->nextDateCalculator->advance($profile, $issueDate);
                $profile->save();
            }

            throw $e;
        }

        // The draft was rendered with the PREVIEW number; the issued number
        // can differ (concurrent issue, reservation floor). Re-resolve every
        // number-derived field - a stale variable symbol makes the bank
        // payment match the wrong invoice.
        if ($issued->number && $issued->number !== $previewNumber) {
            $issued->variable_symbol = BankSymbolNormalizer::variableSymbol(
                $this->placeholders->resolve($vsTemplate, $issueDate, $issued->number, $vsTemplate)
            ) ?? BankSymbolNormalizer::variableSymbol($issued->number);
            $issued->note_above_lines = $this->placeholders->resolve(
                $profile->note_above_lines,
                $issueDate,
                $issued->number,
                $vsTemplate,
            );
            $sourceLines = $profile->lines->values();
            foreach ($issued->lines as $line) {
                if (! $line instanceof BusinessDocumentLine) {
                    continue;
                }
                $source = $sourceLines->get((int) $line->sort_order);
                if (! $source instanceof BusinessRecurringProfileLine) {
                    continue;
                }
                $line->name = $this->placeholders->resolve($source->name, $issueDate, $issued->number, $vsTemplate);
                $line->description = $this->placeholders->resolve($source->description, $issueDate, $issued->number, $vsTemplate);
                $line->save();
            }
        }

        if ($issued->number && $profile->title) {
            $issued->title = $this->placeholders->resolve(
                $profile->title,
                $issueDate,
                $issued->number,
                $issued->variable_symbol
            );
        }

        if ($issued->isDirty()) {
            $issued->save();
        }

        $profile->last_generated_document_id = $issued->id;
        $profile->last_generated_at = now();
        $profile->next_issue_date = $this->nextDateCalculator->advance($profile, $issueDate);
        $profile->save();

        AuditLog::log('business_recurring.generated', 'business_recurring_profile', $profile->id, [
            'document_id' => $issued->id,
            'number' => $issued->number,
        ]);

        return $issued->fresh(['lines', 'contact', 'store']);
    }
}
